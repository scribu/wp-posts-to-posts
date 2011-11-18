<?php

class P2P_URL_Query {

	function init() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'request', array( __CLASS__, 'request' ) );
	}

	function query_vars( $public_qv ) {
		$public_qv[] = 'connected_type';
		$public_qv[] = 'connected';

		return $public_qv;
	}

	function request( $request ) {
		if ( !isset( $request['connected_type'] ) || !isset( $request['connected'] ) )
			return $request;

		$connected_arg = _p2p_pluck( $request, 'connected' );

		$ctype = p2p_type( _p2p_pluck( $request, 'connected_type' ) );
		if ( !$ctype )
			return array( 'year' => 2525 );

		$directed = $ctype->find_direction( $connected_arg );
		if ( !$directed )
			return array( 'year' => 2525 );

		return $directed->get_connected_args( $connected_arg, $request );
	}
}

P2P_URL_Query::init();

