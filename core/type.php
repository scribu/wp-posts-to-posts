<?php

class P2P_Connection_Type {

	private static $instances = array();

	public function register( $args ) {
		$args = wp_parse_args( $args, array(
			'id' => false,
			'from' => '',
			'to' => '',
			'from_query_vars' => array(),
			'to_query_vars' => array(),
			'data' => array(),
			'reciprocal' => false,
			'cardinality' => 'many-to-many',
			'prevent_duplicates' => true,
			'sortable' => false,
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

		$id =& $args['id'];

		if ( !$id ) {
			$id = md5( serialize( wp_array_slice_assoc( $args, array( 'from_query_vars', 'to_query_vars', 'data' ) ) ) );
		}

		if ( isset( self::$instances[ $id ] ) ) {
			trigger_error( 'Connection type is already defined.', E_USER_NOTICE );
		}

		return self::$instances[ $id ] = new P2P_Connection_Type( $args );
	}

	public function get_all_instances() {
		return self::$instances;
	}

	public function get_instance( $hash ) {
		if ( isset( self::$instances[ $hash ] ) )
			return self::$instances[ $hash ];

		return false;
	}


	protected $args;
	protected $indeterminate;

	protected function __construct( $args ) {
		$this->args = $args;

		$common = array_intersect( $this->from, $this->to );

		if ( !empty( $common ) )
			$this->indeterminate = true;

		$this->args['title'] = P2P_Util::expand_title( $this->args['title'], $this->from, $this->to );
	}

	public function __get( $key ) {
		if ( 'from' == $key || 'to' == $key )
			return $this->args[ "{$key}_query_vars" ]['post_type'];

		if ( 'indeterminate' == $key )
			return $this->indeterminate;

		return $this->args[$key];
	}

	public function __isset( $key ) {
		return isset( $this->args[$key] );
	}

	public function __call( $method, $args ) {
		$directed = $this->find_direction( $args[0] );
		if ( !$directed )
			return false;

		return call_user_func_array( array( $directed, $method ), $args );
	}

	/**
	 * Set the direction.
	 *
	 * @param string $direction Can be 'from', 'to' or 'any'.
	 *
	 * @return object P2P_Directed_Connection_Type instance
	 */
	public function set_direction( $direction ) {
		if ( !in_array( $direction, array( 'from', 'to', 'any' ) ) )
			return false;

		if ( $orderby_key = P2P_Util::get_orderby_key( $this->sortable, $direction ) )
			return new P2P_Ordered_Connection_Type( $this, $direction, $orderby_key );

		return new P2P_Directed_Connection_Type( $this, $direction );
	}

	/**
	 * Attempt to guess direction based on a post id or post type.
	 *
	 * @param int|string $arg A post id or a post type.
	 * @param bool Whether to return an instance of P2P_Directed_Connection_Type or just the direction
	 *
	 * @return bool|object|string False on failure, P2P_Directed_Connection_Type instance or direction on success.
	 */
	public function find_direction( $arg, $instantiate = true ) {
		$post_type = P2P_Util::find_post_type( $arg );
		if ( !$post_type )
			return false;

		$direction = P2P_Util::get_direction( $post_type, $this->from, $this->to );
		if ( !$direction )
			return false;

		if ( $this->indeterminate )
			$direction = $this->reciprocal ? 'any' : 'from';

		if ( $instantiate )
			return $this->set_direction( $direction );

		return $direction;
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

		$directed = $this->find_direction( $post_type );
		if ( !$directed )
			return false;

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

		$q = $directed->get_connected( array_keys( $posts ), $extra_qv );

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
	 * Get a list of posts connected to other posts connected to a post.
	 *
	 * @param int|array $post_id A post id or array of post ids
	 * @param array $extra_qv Additional query variables to use.
	 *
	 * @return bool|object False on failure; A WP_Query instance on success.
	 */
	public function get_related( $post_id, $extra_qv = array() ) {
		$post_id = (array) $post_id;

		$connected = $this->get_connected( $post_id, $extra_qv );
		if ( !$connected )
			return false;

		if ( !$connected->have_posts() )
			return $connected;

		$connected_ids = wp_list_pluck( $connected->posts, 'ID' );

		return $this->get_connected( $connected_ids, array(
			'post__not_in' => $post_id,
		) );
	}

	/**
	 * Get the previous post in an ordered connection.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 *
	 * @return bool|object False on failure, post object on success
	 */
	public function get_previous( $from, $to ) {
		return $this->get_adjacent( $from, $to, -1 );
	}

	/**
	 * Get the next post in an ordered connection.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 *
	 * @return bool|object False on failure, post object on success
	 */
	public function get_next( $from, $to ) {
		return $this->get_adjacent( $from, $to, +1 );
	}

	/**
	 * Get another post in an ordered connection.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 * @param int The position relative to the first parameter
	 *
	 * @return bool|object False on failure, post object on success
	 */
	public function get_adjacent( $from, $to, $which ) {
		$directed = $this->find_direction( $to );
		if ( !$directed )
			return false;

		if ( !method_exists( $directed, 'get_orderby_key' ) )
			return false;

		$p2p_id = $directed->get_p2p_id( $to, $from );
		if ( !$p2p_id )
			return false;

		$order = (int) p2p_get_meta( $p2p_id, $directed->get_orderby_key(), true );

		$adjacent = $directed->get_connected( $to, array(
			'connected_meta' => array(
				array(
					'key' => $directed->get_orderby_key(),
					'value' => $order + $which
				)
			)
		) )->posts;

		if ( empty( $adjacent ) )
			return false;

		return $adjacent[0];
	}

	/**
	 * Delete a connection.
	 *
	 * @param int p2p_id
	 */
	public function delete_connection( $p2p_id ) {
		return P2P_Storage::delete( $p2p_id );
	}
}

