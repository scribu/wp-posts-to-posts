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
		return $this->set_direction( _p2p_flip_direction( $this->direction ) );
	}

	public function get( $side, $key ) {
		switch ( $side ) {
		case 'current':
			$map = array(
				'to' => 'to',
				'from' => 'from',
				'any' => 'from'
			);
			break;
		case 'opposite':
			$map = array(
				'to' => 'from',
				'from' => 'to',
				'any' => 'to'
			);
			break;
		}

		$arg = $this->ctype->$key;

		return $arg[ $map[ $this->direction ] ];
	}

	private function abstract_query( $qv, $which, $output = 'abstract' ) {
		$side = $this->get( $which, 'side' );

		$qv = $this->get_final_qv( $qv, $which );
		$query = $side->do_query( $qv );

		if ( 'raw' == $output )
			return $query;

		return $side->get_list( $query );
	}

	protected function recognize( $item, $which = 'current' ) {
		return $this->get( $which, 'side' )->item_recognize( $item );
	}

	public function get_final_qv( $q, $which = 'current' ) {
		$side = $this->get( $which, 'side' );

		return $side->get_base_qv( $side->translate_qv( $q ) );
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
		$args = array_merge( $extra_qv, array(
			'connected_type' => $this->name,
			'connected_direction' => $this->direction,
			'connected_items' => $item
		) );

		return $this->abstract_query( $args, 'opposite', $output );
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
	 * @param mixed $arg The item to find connection candidates for.
	 */
	public function get_connectable( $arg, $extra_qv = array(), $output = 'raw' ) {
		$side = $this->get( 'opposite', 'side' );

		$item = $this->recognize( $arg );

		$extra_qv['p2p:exclude'] = $this->get_non_connectable( $item, $extra_qv );

		$qv = apply_filters( 'p2p_connectable_args', $extra_qv, $this, $item->get_object() );

		return $this->abstract_query( $qv, 'opposite', $output );
	}

	protected function get_non_connectable( $item, $extra_qv ) {
		$to_exclude = array();

		if ( 'one' == $this->get( 'current', 'cardinality' ) ) {
			$to_check = 'any';
		} elseif ( !$this->duplicate_connections ) {
			$to_check = $item;
		} else {
			return $to_exclude;
		}

		$extra_qv['fields'] = 'ids';
		$already_connected = $this->get_connected( $to_check, $extra_qv, 'abstract' )->items;

		_p2p_append( $to_exclude, $already_connected );

		return $to_exclude;
	}

	private function _check_params( $from_arg, $to_arg ) {
		$from = $this->recognize( $from_arg, 'current' );
		if ( !$from )
			return new WP_Error( 'first_parameter', 'Invalid first parameter.' );

		if ( 'any' == $to_arg ) {
			$to = 'any';
		} else {
			$to = $this->recognize( $to_arg, 'opposite' );
			if ( !$to )
				return new WP_Error( 'second_parameter', 'Invalid second parameter.' );
		}

		return compact( 'from', 'to' );
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
		$r = $this->_check_params( $from, $to );
		if ( is_wp_error( $r ) )
			return $r;

		extract( $r );

		if ( !$this->self_connections && $from->get_id() == $to->get_id() )
			return new WP_Error( 'self_connection', 'Connection between an element and itself is not allowed.' );

		if ( !$this->duplicate_connections && $this->get_p2p_id( $from, $to ) )
			return new WP_Error( 'duplicate_connection', 'Duplicate connections are not allowed.' );

		if ( 'one' == $this->get( 'opposite', 'cardinality' ) ) {
			if ( $this->has_connections( $from ) )
				return new WP_Error( 'cardinality_opposite', 'Cardinality problem (opposite).' );
		}

		if ( 'one' == $this->get( 'current', 'cardinality' ) ) {
			if ( $this->flip_direction()->has_connections( $to ) )
				return new WP_Error( 'cardinality_current', 'Cardinality problem (current).' );
		}

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

	protected function has_connections( $item ) {
		$extra_qv = array( 'p2p:per_page' => 1 );

		$connections = $this->get_connected( $item, $extra_qv, 'abstract' );

		return !empty( $connections->items );
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
		$r = $this->_check_params( $from, $to );
		if ( is_wp_error( $r ) )
			return $r;

		return $this->delete_connections( $r );
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

