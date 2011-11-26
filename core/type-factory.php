<?php

class P2P_Connection_Type_Factory {
	private static $instances = array();

	public static function register( $args ) {
		$args = wp_parse_args( $args, array(
			'name' => false,
			'from' => 'post',
			'to' => 'post',
			'data' => array(),
			'cardinality' => 'many-to-many',
			'prevent_duplicates' => true,
			'sortable' => false,
			'title' => '',
			'reciprocal' => false,
		) );

		$sides = array();

		foreach ( array( 'from', 'to' ) as $direction ) {
			$side = (array) $args[ $direction ];

			if ( !isset( $side['object'] ) )
				$side = array( 'object' => 'post', 'post_type' => $side );

			if ( 'post' == $side['object'] && isset( $args["{$direction}_query_vars"] ) )
				$side['query_vars'] = _p2p_pluck( $args, "{$direction}_query_vars" );

			$class = 'P2P_Side_' . ucfirst( $side['object'] );

			$sides[ $direction ] = new $class( $side );
		}

		if ( !$args['name'] ) {
			$to_hash = array_map( 'get_object_vars', $sides );
			$to_hash['data'] = $args['data'];

			$args['name'] = md5( serialize( $to_hash ) );
		}

		if ( $sides['from']->object == $sides['to']->object && 'post' == $sides['from']->object )
			$class = 'P2P_Connection_Type';
		else
			$class = 'Generic_Connection_Type';

		$ctype = new $class( $sides, $args );

		if ( isset( self::$instances[ $ctype->name ] ) ) {
			trigger_error( 'Connection type is already defined.', E_USER_NOTICE );
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

