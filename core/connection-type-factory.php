<?php

class P2P_Connection_Type_Factory {

	private static $instances = array();

	public static function register( $args ) {
		$defaults = array(
			'name' => false,
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
		);

		$args = shortcode_atts( $defaults, $args );

		if ( strlen( $args['name'] ) > 44 ) {
			trigger_error( sprintf( "Connection name '%s' is longer than 44 characters.", $args['name'] ), E_USER_WARNING );
			return false;
		}

		$sides = array();

		foreach ( array( 'from', 'to' ) as $direction ) {
			$sides[ $direction ] = self::create_side( $args, $direction );
		}

		if ( !$args['name'] ) {
			trigger_error( "Connection types without a 'name' parameter are deprecated.", E_USER_WARNING );
			$args['name'] = self::generate_name( $sides, $args );
		}

		$args = apply_filters( 'p2p_connection_type_args', $args, $sides );

		$class = self::get_ctype_class( $sides, _p2p_pluck( $args, 'reciprocal' ) );

		$ctype = new $class( $args, $sides );

		self::$instances[ $ctype->name ] = $ctype;

		return $ctype;
	}

	private static function create_side( &$args, $direction ) {
		$object = _p2p_pluck( $args, $direction );

		if ( in_array( $object, array( 'user', 'attachment' ) ) )
			$object_type = $object;
		else
			$object_type = 'post';

		$query_vars = _p2p_pluck( $args, $direction . '_query_vars' );

		if ( 'post' == $object_type )
			$query_vars['post_type'] = (array) $object;

		$class = 'P2P_Side_' . ucfirst( $object_type );

		return new $class( $query_vars );
	}

	private static function generate_name( $sides, $args ) {
		$vals = array(
			$sides['from']->get_object_type(),
			$sides['to']->get_object_type(),
			$sides['from']->query_vars,
			$sides['to']->query_vars,
			$args['data']
		);

		return md5( serialize( $vals ) );
	}

	private static function get_ctype_class( $sides, $reciprocal ) {
		if ( $sides['from']->is_same_type( $sides['to'] ) &&
		     $sides['from']->is_indeterminate( $sides['to'] ) ) {
			if ( $reciprocal )
				$class = 'P2P_Reciprocal_Connection_Type';
			else
				$class = 'P2P_Indeterminate_Connection_Type';
		} else {
			$class = 'P2P_Connection_Type';
		}

		return $class;
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

