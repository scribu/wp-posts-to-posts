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

	/**
	 * Get connection direction.
	 *
	 * @param int|string $arg A post id or a post type.
	 *
	 * @return bool|string False on failure, 'any', 'to' or 'from' on success.
	 */
	public function find_direction( $arg ) {
		if ( $post_id = (int) $arg ) {
			$post = get_post( $post_id );
			if ( !$post )
				return false;
			$post_type = $post->post_type;
		} else {
			$post_type = $arg;
		}

		if ( in_array( $post_type, $this->from ) ) {
			if ( $this->reciprocal && in_array( $post_type, $this->to ) )
				$direction = 'any';
			else
				$direction = 'from';
		} elseif ( in_array( $post_type, $this->to ) ) {
			$direction = 'to';
		} else {
			return false;
		}

		return new P2P_Directed_Connection_Type( $this, $direction );
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
	 * Get a list of posts that are connected to a given post.
	 *
	 * @param int $post_id A post id.
	 * @param array $extra_qv Additional query variables to use.
	 *
	 * @return bool|object False on failure; A WP_Query instance on success.
	 */
	public function get_connected( $post_id, $extra_qv = array() ) {
		$directed = $this->find_direction( $post_id );
		if ( !$directed )
			return false;

		return $directed->get_connected( $post_id, $extra_qv );
	}

	/**
	 * Get a list of posts that could be connected to a given post.
	 *
	 * @param int $post_id A post id.
	 * @param array $extra_qv Additional query variables to use.
	 *
	 * @return bool|object False on failure; A WP_Query instance on success.
	 */
	public function get_connectable( $post_id, $extra_qv = array() ) {
		$directed = $this->find_direction( $post_id );
		if ( !$directed )
			return false;

		return $directed->get_connectable( $post_id, $extra_qv );
	}

	/**
	 * Connect two posts.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 *
	 * @return int p2p_id
	 */
	public function connect( $from, $to ) {
		$directed = $this->find_direction( $from );
		if ( !$directed )
			return false;

		return $directed->connect( $from, $to );
	}

	/**
	 * Disconnect two posts.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 */
	public function disconnect( $from, $to ) {
		$directed = $this->find_direction( $from );
		if ( !$directed )
			return false;

		return $directed->disconnect( $from, $to );
	}

	/**
	 * Delete all connections for a certain post.
	 *
	 * @param int The post id.
	 */
	public function disconnect_all( $from ) {
		$directed = $this->find_direction( $from );
		if ( !$directed )
			return false;

		return $directed->disconnect_all( $from );
	}

	/**
	 * Delete a connection.
	 *
	 * @param int p2p_id
	 */
	public function delete_connection( $p2p_id ) {
		return P2P_Storage::delete( $p2p_id );
	}

	public function get_p2p_id( $from, $to ) {
		$directed = $this->find_direction( $from );
		if ( !$directed )
			return false;

		return $directed->get_p2p_id( $from, $to );
	}
}

