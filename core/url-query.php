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

		$ctype = p2p_type( _p2p_pluck( $request, 'connected_type' ) );
		if ( !$ctype )
			return $request;

		return $ctype->get_connected_args( _p2p_pluck( $request, 'connected' ), $request );
	}
}

P2P_URL_Query::init();

