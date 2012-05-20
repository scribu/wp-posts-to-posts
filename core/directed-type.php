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

	public function flip_direction() {
		if ( 'any' == $this->direction )
			return $this;

		$direction = ( 'to' == $this->direction ) ? 'from' : 'to';

		return $this->set_direction( $direction );
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

	private function abstract_query( $qv, $side, $output = 'abstract' ) {
		$query = $side->do_query( $qv );

		if ( 'raw' == $output )
			return $query;

		$class = str_replace( 'P2P_Side_', 'P2P_List_', get_class( $side ) );

		return new $class( $query );
	}

	/**
	 * Get a list of posts connected to other posts connected to a post.
	 *
	 * @param mixed $item An object, an object id or an array of such.
	 * @param array $extra_qv Additional query variables to use.
	 *
	 * @return bool|object False on failure; A WP_Query instance on success.
	 */
	public function get_related( $item, $extra_qv = array(), $output = 'raw' ) {
		$extra_qv['fields'] = 'ids';

		$connected = $this->get_connected( $item, $extra_qv, 'abstract' );

		$additional_qv = array( 'p2p:exclude' => _p2p_normalize( $item ) );

		return $this->flip_direction()->get_connected( $connected->items, $additional_qv, $output );
	}

	/**
	 * Get a list of items that are connected to a given item.
	 *
	 * @param mixed $item An object, an object id or an array of such.
	 * @param array $extra_qv Additional query variables to use.
	 *
	 * @return object
	 */
	public function get_connected( $item, $extra_qv = array(), $output = 'raw' ) {
		$side = $this->get_opposite( 'side' );

		$args = array_merge( $side->translate_qv( $extra_qv ), array(
			'connected_items' => $item
		) );

		return $this->abstract_query( $this->get_connected_args( $args ), $side, $output );
	}

	public function get_connected_args( $q ) {
		$q = wp_parse_args( $q, array(
			'p2p:context' => false
		) );

		if ( $orderby_key = $this->get_orderby_key() ) {
			$q = wp_parse_args( $q, array(
				'connected_orderby' => $orderby_key,
				'connected_order' => 'ASC',
				'connected_order_num' => true,
			) );
		}

		$q = array_merge( $this->get_opposite( 'side' )->get_base_qv( $q ), array(
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

	/**
	 * Get a list of items that could be connected to a given item.
	 *
	 * @param int $post_id A post id.
	 */
	public function get_connectable( $item_id, $extra_qv = array() ) {
		$side = $this->get_opposite( 'side' );

		$extra_qv['p2p:exclude'] = $this->get_non_connectable( $item_id );

		$extra_qv = $side->get_base_qv( $side->translate_qv( $extra_qv ) );

		$qv = apply_filters( 'p2p_connectable_args', $extra_qv, $this, $item_id );

		return $this->abstract_query( $qv, $side );
	}

	private function get_non_connectable( $item_id ) {
		$to_exclude = array();

		if ( $this->indeterminate && !$this->self_connections )
			$to_exclude[] = $item_id;

		if ( 'one' == $this->get_current( 'cardinality' ) ) {
			_p2p_append( $to_exclude, $this->get_connections( array(
				'fields' => 'object_id'
			) ) );
		}

		if ( !$this->duplicate_connections ) {
			_p2p_append( $to_exclude, $this->get_connections( array(
				'from' => $item_id,
				'fields' => 'object_id'
			) ) );
		}

		return $to_exclude;
	}

	/**
	 * Connect two items.
	 *
	 * @param mixed The first end of the connection.
	 * @param mixed The second end of the connection.
	 * @param array Additional information about the connection.
	 *
	 * @return int|object p2p_id or WP_Error on failure
	 */
	public function connect( $from, $to, $meta = array() ) {
		$from = $this->get_current( 'side' )->item_id( $from );
		if ( !$from )
			return new WP_Error( 'first_parameter', 'Invalid first parameter.' );

		$to = $this->get_opposite( 'side' )->item_id( $to );
		if ( !$to )
			return new WP_Error( 'second_parameter', 'Invalid second parameter.' );

		if ( !$this->self_connections && $from == $to )
			return new WP_Error( 'self_connection', 'Connection between an element and itself is not allowed.' );

		if ( !$this->duplicate_connections && $this->get_p2p_id( $from, $to ) )
			return new WP_Error( 'duplicate_connection', 'Duplicate connections are not allowed.' );

		if ( 'one' == $this->get_opposite( 'cardinality' ) && $this->connection_exists( compact( 'from' ) ) )
			return new WP_Error( 'cardinality_opposite', 'Cardinality problem.' );

		if ( 'one' == $this->get_current( 'cardinality' ) && $this->connection_exists( compact( 'to' ) ) )
			return new WP_Error( 'cardinality_current', 'Cardinality problem.' );

		$p2p_id = $this->create_connection( array(
			'from' => $from,
			'to' => $to,
			'meta' => array_merge( $meta, $this->data )
		) );

		// Store additional default values
		foreach ( $this->fields as $key => $args ) {
			// (array) null == array()
			foreach ( (array) $this->get_default( $args, $p2p_id ) as $default_value ) {
				p2p_add_meta( $p2p_id, $key, $default_value );
			}
		}

		return $p2p_id;
	}

	protected function get_default( $args, $p2p_id ) {
		if ( isset( $args['default_cb'] ) )
			return call_user_func( $args['default_cb'], p2p_get_connection( $p2p_id ) );

		if ( !isset( $args['default'] ) )
			return null;

		return $args['default'];
	}

	/**
	 * Disconnect two items.
	 *
	 * @param mixed The first end of the connection.
	 * @param mixed The second end of the connection or 'any'.
	 *
	 * @return int|object count or WP_Error on failure
	 */
	public function disconnect( $from, $to ) {
		$from = $this->get_current( 'side' )->item_id( $from );
		if ( !$from )
			return new WP_Error( 'first_parameter', 'Invalid first parameter.' );

		if ( 'any' != $to ) {
			$to = $this->get_opposite( 'side' )->item_id( $to );
			if ( !$to )
				return new WP_Error( 'second_parameter', 'Invalid second parameter.' );
		}

		return $this->delete_connections( compact( 'from', 'to' ) );
	}

	public function get_p2p_id( $from, $to ) {
		return _p2p_first( $this->get_connections( array(
			'from' => $from,
			'to' => $to,
			'fields' => 'p2p_id'
		) ) );
	}

	/**
	 * Transforms $this->get_connections( ... ) into p2p_get_connections( $this->name, ... ) etc.
	 */
	public function __call( $method, $args ) {
		$args[0]['direction'] = $this->direction;

		return call_user_func( 'p2p_' . $method, $this->name, $args[0] );
	}
}

