<?php

class P2P_Connection_Type {

	static $instances = array();

	protected $args;

	protected function __construct( $args ) {
		$this->args = $args;
	}

	public function __get( $key ) {
		return $this->args[$key];
	}

	public function get_instance( $args ) {
		$args = wp_parse_args( $args, array(
			'from' => '',
			'to' => '',
			'data' => array(),
			'sortable' => false,
			'prevent_duplicates' => true,
			'title' => '',
		) );

		foreach ( array( 'from', 'to' ) as $key ) {
			if ( !post_type_exists( $args[$key] ) ) {
				trigger_error( "Invalid post type: $args[$key]", E_USER_WARNING );
				return false;
			}
		}

		$instance = new P2P_Connection_Type( $args );

		self::$instances[] = $instance;

		return $instance;
	}

	/**
	 * Get connection direction.
	 *
	 * @param int|string $arg A post id or a post type.
	 * @param bool $warn Wether to trigger a notice on error.
	 *
	 * @return bool|string False on failure, 'any', 'to' or 'from' on success.
	 */
	public function get_direction( $arg, $warn = true ) {
		if ( $post_id = (int) $arg ) {
			$post = get_post( $post_id );
			if ( !$post )
				return false;
			$post_type = $post->post_type;
		} else {
			$post_type = $arg;
		}

		if ( $post_type == $this->to && $this->from == $post_type )
			return 'any';

		if ( $this->to == $post_type )
			return 'to';

		if ( $this->from == $post_type )
			return 'from';

		if ( $warn ) {
			trigger_error( sprintf( "Invalid post type. Expected '%s' or '%s', but received '%s'.",
				$this->args['from'],
				$this->args['to'],
				$post_type
			), E_USER_WARNING );
		}

		return false;
	}

	public function get_other_post_type( $direction ) {
		return 'from' == $direction ? $this->to : $this->from;
	}

	public function get_title( $direction ) {
		$title = $this->args['title'];

		if ( is_array( $title ) ) {
			$key = ( 'to' == $direction ) ? 'to' : 'from';

			if ( isset( $title[ $key ] ) )
				$title = $title[ $key ];
			else
				$title = '';
		}

		return $title;
	}

	private function get_base_args( $direction, $extra_qv ) {
		return array_merge( $extra_qv, array(
			'post_type' => $this->get_other_post_type( $direction ),
			'suppress_filters' => false,
		) );
	}

	/**
	 * Get a list of posts that are connected to a given post.
	 *
	 * @param int $post_id A post id.
	 * @param array $extra_qv Additional query variables to use
	 *
	 * @return object A WP_Query instance.
	 */
	public function get_connected( $post_id, $extra_qv = array() ) {
		$direction = $this->get_direction( $post_id );
		if ( !$direction )
			return array();

		$args = $this->get_base_args( $direction, $extra_qv );

		_p2p_append( $args, array(
			P2P_Query::get_qv( $direction ) => $post_id,
			'connected_meta' => $this->data,
		) );

		if ( $this->sortable && 'from' == $direction ) {
			_p2p_append( $args, array(
				'connected_orderby' => $this->sortable,
				'connected_order' => 'ASC',
				'connected_order_num' => true,
			) );
		}

		$args = apply_filters( 'p2p_connected_args', $args, $this );

		return new WP_Query( $args );
	}

	/**
	 * Get a list of posts that could be connected to a given post.
	 *
	 * @param int $post_id A post id.
	 * @param int $page A page number.
	 * @param string $search A search string.
	 *
	 * @return object A WP_Query instance.
	 */
	public function get_connectable( $post_id, $extra_qv ) {
		$direction = $this->get_direction( $post_id );
		if ( !$direction )
			return array();

		$args = $this->get_base_args( $direction, $extra_qv );

		if ( $this->prevent_duplicates ) {
			if ( !isset( $args['post__not_in'] ) ) {
				$args['post__not_in'] = array();
			}

			_p2p_append( $args['post__not_in'], P2P_Storage::get( $post_id, $direction, $this->data ) );
		}

		$args = apply_filters( 'p2p_connectable_args', $args, $this );

		return new WP_Query( $args );
	}

	/**
	 * Optimized inner query, after the outer query was executed.
	 *
	 * Populates each of the outer querie's $post objects with a 'connected' property, containing a list of connected posts
	 *
	 * @param object $query WP_Query instance.
	 * @param string|array $search Additional query vars for the inner query.
	 */
	public function each_connected( $query, $search = array() ) {
		if ( empty( $query->posts ) || !is_object( $query->posts[0] ) )
			return;

		$post_type = $query->get( 'post_type' );
		if ( is_array( $post_type ) )
			return;

		$direction = $this->get_direction( $post_type );
		if ( !$direction )
			return;

		$search['post_type'] = $this->get_other_post_type( $direction );

		$prop_name = 'connected';

		$posts = array();

		foreach ( $query->posts as $post ) {
			$post->$prop_name = array();
			$posts[ $post->ID ] = $post;
		}

		// ignore other 'connected' query vars for the inner query
		foreach ( array_keys( P2P_Query::$qv_map ) as $qv )
			unset( $search[ $qv ] );

		$search[ P2P_Query::get_qv( $direction ) ] = array_keys( $posts );

		// ignore pagination
		foreach ( array( 'showposts', 'posts_per_page', 'posts_per_archive_page' ) as $disabled_qv ) {
			if ( isset( $search[ $disabled_qv ] ) ) {
				trigger_error( "Can't use '$disabled_qv' in an inner query", E_USER_WARNING );
			}
		}
		$search['nopaging'] = true;

		$search['ignore_sticky_posts'] = true;

		$q = new WP_Query( $search );

		foreach ( $q->posts as $inner_post ) {
			if ( $inner_post->ID == $inner_post->p2p_from )
				$outer_post_id = $inner_post->p2p_to;
			elseif ( $inner_post->ID == $inner_post->p2p_to )
				$outer_post_id = $inner_post->p2p_from;
			else
				throw new Exception( 'Corrupted data.' );

			if ( $outer_post_id == $inner_post->ID )
				throw new Exception( 'Post connected to itself.' );

			array_push( $posts[ $outer_post_id ]->$prop_name, $inner_post );
		}
	}

	public function connect( $from, $to ) {
		$post_from = get_post( $from );
		$post_to = get_post( $to );

		if ( !$post_from || !$post_to ) {
			return false;
		}

		$args = array( $from, $to );

		if ( $post_from->post_type == $this->to )
			$args = array_reverse( $args );

		$p2p_id = false;

		if ( $this->prevent_duplicates ) {
			$p2p_ids = P2P_Storage::get( $args[0], $args[1], $this->data );

			if ( !empty( $p2p_ids ) )
				$p2p_id = $p2p_ids[0];
		}

		if ( !$p2p_id ) {
			$p2p_id = P2P_Storage::connect( $args[0], $args[1], $this->data );
		}

		return $p2p_id;
	}

	public function disconnect( $post_id ) {
		p2p_disconnect( $post_id, $this->get_direction( $post_id ), $this->data );
	}
}

