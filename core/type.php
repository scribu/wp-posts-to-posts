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

	public function get_instance( $hash ) {
		if ( isset( self::$instances[ $hash ] ) )
			return self::$instances[ $hash ];

		return false;
	}

	public function make_instance( $args ) {
		$args = wp_parse_args( $args, array(
			'from' => '',
			'to' => '',
			'data' => array(),
			'reciprocal' => null,
			'sortable' => false,
			'prevent_duplicates' => true,
			'title' => '',
		) );

		foreach ( array( 'from', 'to' ) as $key ) {
			$args[ $key ] = array_values( array_filter( (array) $args[ $key ], 'post_type_exists' ) );
			if ( empty( $args[ $key ] ) ) {
				trigger_error( "'$key' arg doesn't contain any valid post types", E_USER_WARNING );
				return false;
			}
			sort( $args[ $key ] );
		}

		$common = array_intersect( $args['from'], $args['to'] );
		if ( !empty( $common ) && count( $args['from'] ) + count( $args['to'] ) > 2 ) {
			$args['reciprocal'] = false;
		}

		if ( is_null( $args['reciprocal'] ) )
			$args['reciprocal'] = ( $args['from'] == $args['to'] );

		$hash = md5( serialize( wp_array_slice_assoc( $args, array( 'from', 'to', 'data' ) ) ) );

		if ( isset( self::$instances[ $hash ] ) ) {
			trigger_error( 'Connection type is already defined.', E_USER_NOTICE );
			return self::$instances[ $hash ];
		}

		return self::$instances[ $hash ] = new P2P_Connection_Type( $args );
	}

	/**
	 * Get connection direction.
	 *
	 * @param int|string $arg A post id or a post type.
	 *
	 * @return bool|string False on failure, 'any', 'to' or 'from' on success.
	 */
	public function get_direction( $arg ) {
		if ( $post_id = (int) $arg ) {
			$post = get_post( $post_id );
			if ( !$post )
				return false;
			$post_type = $post->post_type;
		} else {
			$post_type = $arg;
		}

		if ( in_array( $post_type, $this->from ) ) {
			if ( in_array( $post_type, $this->to ) )
				$direction = 'any';
			else
				$direction = 'from';
		} elseif ( in_array( $post_type, $this->to ) ) {
			$direction = 'to';
		} else {
			$direction = false;
		}

		return $direction;
	}

	public function get_other_post_type( $direction ) {
		return 'from' == $direction ? $this->to : $this->from;
	}

	public function can_create_post( $direction ) {
		$ptype = $this->get_other_post_type( $direction );

		if ( count( $ptype ) > 1 )
			return false;

		return current_user_can( get_post_type_object( $ptype[0] )->cap->edit_posts );
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
			'ignore_sticky_posts' => true
		) );
	}

	/**
	 * Get a list of posts that are connected to a given post.
	 *
	 * @param int $post_id A post id.
	 * @param array $extra_qv Additional query variables to use.
	 *
	 * @return bool|object False on failure; A WP_Query instance on success.
	 */
	public function get_connected( $post_id, $extra_qv = array(), $_direction = false ) {
		$direction = $_direction ? $_direction : $this->get_direction( $post_id );
		if ( !$direction )
			return false;

		$args = $this->get_base_args( $direction, $extra_qv );

		_p2p_append( $args, array(
			'connected' => $post_id,
			'connected_direction' => $direction,
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
	 * @param array $extra_qv Additional query variables to use.
	 *
	 * @return bool|object False on failure; A WP_Query instance on success.
	 */
	public function get_connectable( $post_id, $extra_qv, $_direction = false ) {
		$direction = $_direction ? $_direction : $this->get_direction( $post_id );
		if ( !$direction )
			return false;

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
	 * @param string|array $extra_qv Additional query vars for the inner query.
	 */
	public function each_connected( $query, $extra_qv = array() ) {
		if ( empty( $query->posts ) || !is_object( $query->posts[0] ) )
			return;

		$post_type = $query->get( 'post_type' );
		if ( empty( $post_type ) )
			$post_type = 'post';

		$direction = $this->get_direction( $post_type );
		if ( !$direction )
			return;

		$prop_name = 'connected';

		$posts = array();

		foreach ( $query->posts as $post ) {
			$post->$prop_name = array();
			$posts[ $post->ID ] = $post;
		}

		// ignore pagination
		foreach ( array( 'showposts', 'posts_per_page', 'posts_per_archive_page' ) as $disabled_qv ) {
			if ( isset( $extra_qv[ $disabled_qv ] ) ) {
				trigger_error( "Can't use '$disabled_qv' in an inner query", E_USER_WARNING );
			}
		}
		$extra_qv['nopaging'] = true;

		$q = $this->get_connected( array_keys( $posts ), $extra_qv, $direction );

		foreach ( $q->posts as $inner_post ) {
			if ( $inner_post->ID == $inner_post->p2p_from )
				$outer_post_id = $inner_post->p2p_to;
			elseif ( $inner_post->ID == $inner_post->p2p_to )
				$outer_post_id = $inner_post->p2p_from;
			else {
				trigger_error( "Corrupted data for post $inner_post->ID", E_USER_WARNING );
				continue;
			}

			if ( $outer_post_id == $inner_post->ID ) {
				trigger_error( 'Post connected to itself.', E_USER_WARNING );
				continue;
			}

			array_push( $posts[ $outer_post_id ]->$prop_name, $inner_post );
		}
	}

	public function connect( $from, $to, $_direction = false ) {
		$post_from = get_post( $from );
		$post_to = get_post( $to );

		if ( !$post_from || !$post_to ) {
			return false;
		}

		$direction = $_direction ? $_direction : $this->get_direction( $from );

		$args = array( $from, $to );

		if ( 'to' == $direction )
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

	public function disconnect( $post_id, $_direction = false ) {
		$direction = $_direction ? $_direction : $this->get_direction( $post_id );
		P2P_Storage::disconnect( $post_id, $direction, $this->data );
	}

	public function delete_connection( $p2p_id ) {
		return P2P_Storage::delete( $p2p_id );
	}
}

