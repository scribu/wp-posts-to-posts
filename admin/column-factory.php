<?php

class P2P_Column_Factory {

	private static $column_args = array();

	static function register( $p2p_type, $column_args ) {
		if ( isset( self::$column_args[$p2p_type] ) )
			return false;

		if ( !$column_args )
			return false;

		self::$column_args[$p2p_type] = $column_args;

		return true;
	}

	function add_columns() {
		$screen = get_current_screen();

		if ( 'edit' != $screen->base )
			return;

		$post_type = $screen->post_type;

		foreach ( self::$column_args as $p2p_type => $column_args ) {
			$ctype = p2p_type( $p2p_type );

			$directed = $ctype->find_direction( $post_type );
			if ( !$directed )
				continue;

			if ( !( 'any' == $column_args || $directed->get_direction() == $column_args ) )
				continue;

			$column = new P2P_Column( $directed );

			$column->styles();

			add_filter( "manage_{$screen->id}_columns", array( $column, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $column, 'display_column' ), 10, 2 );
		}
	}
}

add_action( 'admin_print_styles', array( 'P2P_Column_Factory', 'add_columns' ) );

