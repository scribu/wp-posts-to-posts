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

	/**
	 * Get a list of posts that are connected to a given post.
	 *
	 * @param int|array $post_id A post id or an array of post ids.
	 * @param array $extra_qv Additional query variables to use.
	 *
	 * @return object
	 */
	public function get_connected( $post_id, $extra_qv = array(), $output = 'raw' ) {
		$args = array_merge( $extra_qv, array(
			'connected_items' => $post_id
		) );

		$side = $this->get_opposite( 'side' );

		$query = $side->do_query( $this->get_connected_args( $args ) );

		if ( 'abstract' == $output )
			$query = $side->abstract_query( $query );

		return $query;
	}

	public function get_connected_args( $q ) {
		if ( $orderby_key = $this->get_orderby_key() ) {
			$q = wp_parse_args( $q, array(
				'connected_orderby' => $orderby_key,
				'connected_order' => 'ASC',
				'connected_order_num' => true,
			) );
		}

		$q = array_merge( $this->get_opposite( 'side' )->get_base_qv(), $q, array(
			'p2p_type' => array( $this->name => $this->get_direction() ),
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

	/** @internal */
	public function get_connections( $post_id ) {
		$side = $this->get_opposite( 'side' );

		$query = $this->get_connected( $post_id, $side->get_connections_qv() );

		return scb_list_fold( $side->abstract_query( $query )->items, 'p2p_id', 'ID' );
	}

	/**
	 * Get a list of posts that could be connected to a given post.
	 *
	 * @param int $post_id A post id.
	 */
	public function get_connectable( $item_id, $page, $search ) {
		$side = $this->get_opposite( 'side' );

		$qv = $side->get_connectable_qv( $item_id, $page, $search, $this->get_non_connectable( $item_id ) );

		$qv = apply_filters( 'p2p_connectable_args', $qv, $this, $item_id );

		return $side->abstract_query( $side->do_query( $qv ) );
	}

	/**
	 * Connect two items.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 * @param array Additional information about the connection.
	 *
	 * @return int p2p_id
	 */
	public function connect( $from, $to, $meta = array() ) {
		if ( !$this->get_current( 'side' )->item_exists( $from ) )
			return false;

		if ( !$this->get_opposite( 'side' )->item_exists( $to ) )
			return false;

		if ( in_array( $to, $this->get_non_connectable( $from ) ) )
			return false;

		return p2p_create_connection( $this->name, array(
			'direction' => $this->direction,
			'from' => $from,
			'to' => $to,
			'meta' => array_merge( $meta, $this->data )
		) );
	}

	private function get_non_connectable( $item_id ) {
		$to_exclude = array();

		if ( $this->indeterminate && !$this->self_connections )
			$to_exclude[] = $item_id;

		if ( 'one' == $this->get_current( 'cardinality' ) ) {
			_p2p_append( $to_exclude, p2p_get_connections( $this->name, array(
				'direction' => $this->direction,
				'fields' => 'object_id'
			) ) );
		}

		if ( $this->prevent_duplicates ) {
			_p2p_append( $to_exclude, p2p_get_connections( $this->name, array(
				'direction' => $this->direction,
				'from' => $item_id,
				'fields' => 'object_id'
			) ) );
		}

		return $to_exclude;
	}

	/**
	 * Disconnect two posts.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 */
	public function disconnect( $from, $to ) {
		return p2p_delete_connections( $this->name, array(
			'direction' => $this->direction,
			'from' => $from,
			'to' => $to,
		) );
	}

	/**
	 * Delete all connections for a certain post.
	 *
	 * @param int The post id.
	 */
	public function disconnect_all( $from ) {
		return p2p_delete_connections( $this->name, array(
			'direction' => $this->direction,
			'from' => $from,
		) );
	}

	/**
	 * For one-to-many connections: replaces existing item with a new one.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 * @param array Additional information about the connection.
	 *
	 * @return int p2p_id
	 */
	public function replace( $from, $to, $meta = array() ) {
		if ( !$this->get_current( 'side' )->item_exists( $from ) )
			return false;

		if ( 'one' != $this->get_current( 'cardinality' ) )
			return false;

		$existing = _p2p_first( $this->get_connections( $from ) );

		if ( $existing ) {
			if ( $existing == $to )
				return;

			$this->disconnect_all( $from );
		}

		return $this->connect( $from, $to, $meta );
	}

	public function get_p2p_id( $from, $to ) {
		return _p2p_first( p2p_get_connections( $this->name, array(
			'direction' => $this->direction,
			'from' => $from,
			'to' => $to,
			'fields' => 'p2p_id'
		) ) );
	}
}

