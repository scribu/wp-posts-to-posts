<?php

class P2P_Connection_Type_Factory {
	private static $instances = array();

	public static function register( $args ) {
		$ctype = new P2P_Connection_Type( $args );

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

