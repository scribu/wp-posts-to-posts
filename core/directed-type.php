<?php

class P2P_Directed_Connection_Type {

	protected $ctype;
	protected $direction;

	function __construct( $ctype, $direction ) {
		$this->ctype = $ctype;
		$this->direction = $direction;
	}

	function __get( $key ) {
		return $this->ctype->$key;
	}

	function __isset( $key ) {
		return isset( $this->ctype->$key );
	}

	public function get_direction() {
		return $this->direction;
	}

	public function set_direction( $direction ) {
		return $this->ctype->set_direction( $direction );
	}

	public function lose_direction() {
		return $this->ctype;
	}

	public function get_opposite( $key ) {
		$direction = ( 'to' == $this->direction ) ? 'from' : 'to';

		return $this->get_arg( $key, $direction );
	}

	public function get_current( $key ) {
		$direction = ( 'to' == $this->direction ) ? 'to' : 'from';

		return $this->get_arg( $key, $direction );
	}

	private function get_arg( $key, $direction ) {
		$arg = $this->ctype->$key;

		return $arg[$direction];
	}

	public function accepts_single_connection() {
		return 'one' == $this->get_opposite( 'cardinality' );
	}

	/**
	 * Get a list of posts that are connected to a given post.
	 *
	 * @param int|array $post_id A post id or an array of post ids.
	 * @param array $extra_qv Additional query variables to use.
	 *
	 * @return object A WP_Query instance
	 */
	public function get_connected( $post_id, $extra_qv = array() ) {
		return $this->get_opposite( 'side' )->get_connected( $this, $post_id, $extra_qv );
	}

	/**
	 * @internal
	 */
	public function get_connections( $post_id ) {
		return $this->get_opposite( 'side' )->get_connections( $this, $post_id );
	}

	/**
	 * Get a list of posts that could be connected to a given post.
	 *
	 * @param int $post_id A post id.
	 */
	public function get_connectable( $post_id, $page, $search ) {
		return $this->get_opposite( 'side' )->get_connectable( $this, $post_id, $page, $search );
	}

	public function cardinality_check( $post_id ) {
		if ( 'one' == $this->get_current( 'cardinality' ) ) {
			$to_check = 'any';
		} elseif ( $this->prevent_duplicates ) {
			$to_check = $post_id;
		} else {
			return false;
		}

		$to_check = array();

		foreach ( P2P_Util::expand_direction( $this->direction ) as $direction ) {
			$to_check = array_merge( $to_check, p2p_get_connections( $this->name, array(
				$direction => $to_check,
				'fields' => ( 'to' == $direction ) ? 'p2p_from' : 'p2p_to'
			) ) );
		}

		return $to_check;
	}

	public function get_p2p_id( $from, $to ) {
		$connected = $this->get_connected( $from, array( 'post__in' => array( $to ) ) );

		if ( !empty( $connected->posts ) )
			return (int) $connected->posts[0]->p2p_id;

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
	public function connect( $from, $to ) {
		if ( !get_post( $from ) || !get_post( $to ) )
			return false;

		$p2p_id = false;

		if ( 'one' == $this->cardinality ) {
			$connected = $this->get_connected( $from, array( 'fields' => 'ids' ) );
			if ( !empty( $connected->posts ) )
				return false;
		}

		if ( $this->prevent_duplicates ) {
			$p2p_id = $this->get_p2p_id( $from, $to );
		}

		if ( !$p2p_id ) {
			$args = array( $from, $to );

			if ( 'to' == $this->direction )
				$args = array_reverse( $args );

			$p2p_id = p2p_create_connection( $this->name, array(
				'from' => $args[0],
				'to' => $args[1],
				'meta' => $this->data
			) );
		}

		return $p2p_id;
	}

	/**
	 * Disconnect two posts.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 */
	public function disconnect( $from, $to ) {
		return p2p_delete_connection( $this->get_p2p_id( $from, $to ) );
	}

	/**
	 * Delete all connections for a certain post.
	 *
	 * @param int The post id.
	 */
	public function disconnect_all( $from ) {
		foreach ( P2P_Util::expand_direction( $this->direction ) as $dir ) {
			p2p_delete_connections( $this->name, array( $dir => $from ) );
		}
	}
}

