<?php

class P2P_URL_Query {

	static function get_custom_qv() {
		return array( 'connected_type', 'connected_items', 'connected_direction' );
	}

	static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );

		if ( is_admin() )
			add_action( 'pre_user_query', array( __CLASS__, 'user_query' ), 9 );
	}

	// Make the query vars public
	static function query_vars( $public_qv ) {
		return array_merge( $public_qv, self::get_custom_qv() );
	}

	// Add the query vars to the global user query (on the user admin screen)
	static function user_query( $query ) {
		if ( !function_exists( 'get_current_screen' ) )
			return;

		$current_screen = get_current_screen();

		if ( $current_screen && 'users' != $current_screen->id )
			return;

		if ( isset( $query->_p2p_capture ) )
			return;

		// Don't overwrite existing P2P query
		if ( isset( $query->query_vars['connected_type'] ) )
			return;

		_p2p_append( $query->query_vars, wp_array_slice_assoc( $_GET,
			P2P_URL_Query::get_custom_qv() ) );
	}
}

P2P_URL_Query::init();

