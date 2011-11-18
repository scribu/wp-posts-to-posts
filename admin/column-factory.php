<?php

class P2P_Column_Factory {

	private static $column_args = array();

	static function register( $ctype_id, $column_args ) {
		if ( isset( self::$column_args[$ctype_id] ) )
			return false;

		if ( !$column_args )
			return false;

		self::$column_args[$ctype_id] = $column_args;

		return true;
	}

	function add_columns() {
		$screen = get_current_screen();

		if ( 'edit' != $screen->base )
			return;

		$post_type = $screen->post_type;

		foreach ( self::$column_args as $ctype_id => $column_args ) {
			$ctype = p2p_type( $ctype_id );

			$directed = $ctype->find_direction( $post_type );
			if ( !$directed )
				continue;

			if ( !( 'any' == $column_args || $directed->get_direction() == $column_args ) )
				continue;

			$column = new P2P_Column( $directed );

			add_filter( "manage_{$screen->id}_columns", array( $column, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $column, 'display_column' ), 10, 2 );
		}
	}
}

add_action( 'admin_head', array( 'P2P_Column_Factory', 'add_columns' ) );

