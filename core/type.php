<?php

class P2P_Connection_Type {

	static $instances = array();

	protected $args;

	public function make_instance( $args ) {
		$args = wp_parse_args( $args, array(
			'from' => '',
			'to' => '',
			'from_query_vars' => array(),
			'to_query_vars' => array(),
			'data' => array(),
			'reciprocal' => null,
			'sortable' => false,
			'prevent_duplicates' => true,
			'title' => '',
		) );

		foreach ( array( 'from', 'to' ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$args["{$key}_query_vars"]['post_type'] = (array) $args[ $key ];
				unset( $args[ $key ] );
			}

			if ( empty( $args["{$key}_query_vars"]['post_type'] ) )
				$args["{$key}_query_vars"]['post_type'] = array( 'post' );
		}

		$hash = md5( serialize( wp_array_slice_assoc( $args, array( 'from_query_vars', 'to_query_vars', 'data' ) ) ) );

		if ( isset( self::$instances[ $hash ] ) ) {
			trigger_error( 'Connection type is already defined.', E_USER_NOTICE );
			return self::$instances[ $hash ];
		}

		return self::$instances[ $hash ] = new P2P_Connection_Type( $args );
	}

	protected function __construct( $args ) {
		$this->args = $args;

		$common = array_intersect( $this->from, $this->to );

		if ( !empty( $common ) && count( $this->from ) + count( $this->to ) > 2 )
			$this->args['reciprocal'] = false;

		if ( is_null( $args['reciprocal'] ) )
			$this->args['reciprocal'] = ( $this->from == $this->to );
	}

	public function __get( $key ) {
		if ( in_array( $key, array( 'from', 'to' ) ) )
			return $this->args[ "{$key}_query_vars" ]['post_type'];

		return $this->args[$key];
	}

	public function get_instance( $hash ) {
		if ( isset( self::$instances[ $hash ] ) )
			return self::$instances[ $hash ];

		return false;
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

	private function get_base_args( $direction, $extra_qv ) {
		$base_qv = ( 'from' == $direction ) ? $this->to_query_vars : $this->from_query_vars;

		return array_merge( $extra_qv, $base_qv, array(
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
			$connected = $this->get_connected( $post_id, array( 'fields' => 'ids' ), $direction );

			if ( !isset( $args['post__not_in'] ) ) {
				$args['post__not_in'] = array();
			}

			_p2p_append( $args['post__not_in'], $connected->posts );
		}

		$args = apply_filters( 'p2p_connectable_args', $args, $this );

		return new WP_Query( $args );
	}

	public function get_p2p_id( $from, $to, $_direction = false ) {
		$connected = $this->get_connected( $from, array( 'post__in' => array( $to ) ), $_direction );

		if ( !empty( $connected->posts ) )
			return $connected->posts[0]->p2p_id;

		return false;
	}

	/**
	 * Connect two posts.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 *
	 * @return int p2p_id
	 */
	public function connect( $from, $to, $_direction = false ) {
		$post_from = get_post( $from );
		$post_to = get_post( $to );

		if ( !$post_from || !$post_to ) {
			return false;
		}

		$direction = $_direction ? $_direction : $this->get_direction( $from );

		$p2p_id = false;

		if ( $this->prevent_duplicates ) {
			$p2p_id = $this->get_p2p_id( $from, $to, $direction );
		}

		if ( !$p2p_id ) {
			$args = array( $from, $to );

			if ( 'to' == $direction )
				$args = array_reverse( $args );

			$p2p_id = P2P_Storage::connect( $args[0], $args[1], $this->data );
		}

		return $p2p_id;
	}

	/**
	 * Disconnect two posts.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 */
	public function disconnect( $from, $to, $_direction = false ) {
		$direction = $_direction ? $_direction : $this->get_direction( $from );
		if ( !$direction )
			return false;

		P2P_Storage::delete( $this->get_p2p_id( $from, $to, $direction ) );
	}

	/**
	 * Delete all connections for a certain post.
	 *
	 * @param int The post id.
	 */
	public function disconnect_all( $from, $_direction ) {
		$direction = $_direction ? $_direction : $this->get_direction( $from );

		$connected = $this->get_connected( $from, array(), $direction );

		foreach ( wp_list_pluck( $connected->posts, 'p2p_id' ) as $p2p_id )
			P2P_Storage::delete( $p2p_id );
	}

	/**
	 * Delete a connection.
	 *
	 * @param int p2p_id
	 */
	public function delete_connection( $p2p_id ) {
		return P2P_Storage::delete( $p2p_id );
	}


	public function can_create_post( $direction ) {
		$ptype = $this->get_other_post_type( $direction );

		if ( count( $ptype ) > 1 )
			return false;

		return current_user_can( get_post_type_object( $ptype[0] )->cap->edit_posts );
	}
}

