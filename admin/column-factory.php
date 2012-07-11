<?php

class P2P_Column_Factory {

	private static $column_args = array();

	static function init() {
		add_filter( 'p2p_connection_type_args', array( __CLASS__, 'filter_args' ) );

		add_action( 'admin_print_styles', array( __CLASS__, 'add_columns' ) );
	}

	static function filter_args( $args ) {
		if ( isset( $args['admin_column'] ) ) {
			$column_args = _p2p_pluck( $args, 'admin_column' );
		} else {
			$column_args = false;
		}

		self::register( $args['name'], $column_args );

		return $args;
	}

	static function register( $p2p_type, $column_args ) {
		if ( isset( self::$column_args[$p2p_type] ) )
			return false;

		if ( !$column_args )
			return false;

		self::$column_args[$p2p_type] = $column_args;

		return true;
	}

	static function add_columns() {
		$screen = get_current_screen();

		$screen_map = array(
			'edit' => 'post',
			'users' => 'user'
		);

		if ( !isset( $screen_map[ $screen->base ] ) )
			return;

		$object_type = $screen_map[ $screen->base ];

		foreach ( self::$column_args as $p2p_type => $column_args ) {
			$ctype = p2p_type( $p2p_type );

			$direction = _p2p_compress_direction( $ctype->find_direction_from_post_type( $screen->post_type ) );

			if ( !$direction )
				continue;

			$directed = $ctype->set_direction( $direction )->flip_direction();

			if ( !( 'any' == $column_args || $directed->get_direction() == $column_args ) )
				continue;

			$class = 'P2P_Column_' . ucfirst( $object_type );
			$column = new $class( $directed );

			$column->styles();
		}
	}
}

P2P_Column_Factory::init();

