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
	 * @return object
	 */
	public function get_connected( $post_id, $extra_qv = array() ) {
		$args = array_merge( $extra_qv, array(
			'connected_type' => $this->name,
			'connected_items' => $post_id
		) );

		return $this->get_opposite( 'side' )->get_connected( $args );
	}

	// called from P2P_Query
	public function get_connected_args( $q ) {
		if ( $orderby_key = $this->get_orderby_key() ) {
			$q = wp_parse_args( $q, array(
				'connected_orderby' => $orderby_key,
				'connected_order' => 'ASC',
				'connected_order_num' => true,
			) );
		}

		$q = array_merge( $this->get_opposite( 'side' )->query_vars, $q, array(
			'p2p_type' => $this->name,
			'connected_direction' => $this->get_direction(),
		) );

		$q = array_merge_recursive( $q, array(
			'connected_meta' => $this->data
		) );

		return apply_filters( 'p2p_connected_args', $q, $this, $q['connected_items'] );
	}

	public function get_orderby_key() {
		if ( !$this->sortable || 'any' == $this->direction )
			return false;

		if ( 'any' == $this->sortable || $this->direction == $this->sortable )
			return '_order_' . $this->direction;

		// Back-compat
		if ( 'from' == $this->direction )
			return $this->sortable;

		return false;
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

	/**
	 * Connect two posts.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 *
	 * @return int p2p_id
	 */
	public function connect( $from, $to ) {
		// TODO
		if ( !get_post( $from ) || !get_post( $to ) )
			return false;

		$p2p_id = false;

		if ( 'one' == $this->get_current( 'cardinality' ) ) {
			$connected = $this->get_connections( $from, array( 'fields' => 'ids' ) );
			if ( !empty( $connected ) )
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

	protected function check_against( $post_id ) {
		if ( 'one' == $this->get_current( 'cardinality' ) ) {
			return 'any';
		} elseif ( $this->prevent_duplicates ) {
			return $post_id;
		} else {
			return false;
		}
	}

	public function cardinality_check( $post_id ) {
		$against = $this->check_against( $post_id );

		if ( !$against )
			return false;

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
		foreach ( P2P_Util::expand_direction( $this->direction ) as $direction ) {
			$args = array( $from, $to );
			if ( 'to' == $direction ) {
				$args = array_reverse( $args );
			}

			$ids = p2p_get_connections( $this->name, array(
				'from' => $args[0],
				'to' => $args[1],
				'fields' => 'p2p_id'
			) );

			if ( !empty( $ids ) )
				return reset( $ids );
		}

		return false;
	}
}

