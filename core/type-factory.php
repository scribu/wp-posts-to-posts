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
				$validated = array();

				foreach ( (array) $object as $ptype ) {
					if ( !post_type_exists( $ptype ) ) {
						trigger_error( "Post type '$ptype' is not defined." );
					} else {
						$validated[] = $ptype;
					}
				}

				if ( empty( $validated ) )
					$validated = array( 'post' );

				$args[ $direction . '_query_vars' ]['post_type'] = $validated;
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

		$ctype = new P2P_Connection_Type( $args );

		if ( isset( self::$instances[ $ctype->name ] ) ) {
			trigger_error( "Connection type '$ctype->name' is already defined.", E_USER_NOTICE );
		}

		self::$instances[ $ctype->name ] = $ctype;

		return $ctype;
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

