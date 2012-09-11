<?php

class P2P_Connection_Type_Factory {

	private static $instances = array();

	public static function register( $args ) {
		if ( isset( $args['name'] ) ) {
			if ( strlen( $args['name'] ) > 44 ) {
				trigger_error( sprintf( "Connection name '%s' is longer than 44 characters.", $args['name'] ), E_USER_WARNING );
				return false;
			}
		} else {
			trigger_error( "Connection types without a 'name' parameter are deprecated.", E_USER_WARNING );
		}

		$args = wp_parse_args( $args, array(
			'name' => false,
			'from_object' => 'post',
			'to_object' => 'post',
			'from' => 'post',
			'to' => 'post',
			'from_query_vars' => array(),
			'to_query_vars' => array(),
			'fields' => array(),
			'data' => array(),
			'cardinality' => 'many-to-many',
			'duplicate_connections' => false,
			'self_connections' => false,
			'sortable' => false,
			'title' => array(),
			'from_labels' => '',
			'to_labels' => '',
			'reciprocal' => false,
		) );

		$sides = array();

		foreach ( array( 'from', 'to' ) as $direction ) {
			$object = _p2p_pluck( $args, $direction );

			if ( 'user' == $object )
				$args[ $direction . '_object' ] = 'user';
			elseif ( 'attachment' == $object )
				$args[ $direction . '_object' ] = 'attachment';

			if ( 'post' == $args[ $direction . '_object' ] ) {
				$args[ $direction . '_query_vars' ]['post_type'] = (array) $object;
			}
		}

		if ( !$args['name'] ) {
			$args['name'] = md5( serialize( array_values( wp_array_slice_assoc( $args, array(
				'from_object', 'to_object',
				'from_query_vars', 'to_query_vars',
				'data'
			) ) ) ) );
		}

		$args = apply_filters( 'p2p_connection_type_args', $args );

		$sides = self::create_sides( $args );

		$reciprocal = _p2p_pluck( $args, 'reciprocal' );

		if ( $sides['from']->is_same_type( $sides['to'] ) && $sides['from']->is_indeterminate( $sides['to'] ) ) {
			if ( $reciprocal )
				$class = 'P2P_Reciprocal_Connection_Type';
			else
				$class = 'P2P_Indeterminate_Connection_Type';
		} else {
			$class = 'P2P_Connection_Type';
		}

		$ctype = new $class( $sides, $args );

		if ( isset( self::$instances[ $ctype->name ] ) ) {
			trigger_error( "Connection type '$ctype->name' is already defined.", E_USER_NOTICE );
		}

		self::$instances[ $ctype->name ] = $ctype;

		return $ctype;
	}

	private static function create_sides( &$args ) {
		$sides = array();

		foreach ( array( 'from', 'to' ) as $direction ) {
			$object_type = _p2p_pluck( $args, $direction . '_object' );

			$class = 'P2P_Side_' . ucfirst( $object_type );

			$sides[ $direction ] = new $class( _p2p_pluck( $args, $direction . '_query_vars' ) );
		}

		return $sides;
	}

	public static function get_all_instances() {
		return self::$instances;
	}

	public static function get_instance( $hash ) {
		if ( isset( self::$instances[ $hash ] ) )
			return self::$instances[ $hash ];

		return false;
	}
}

