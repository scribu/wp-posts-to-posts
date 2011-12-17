<?php

class P2P_URL_Query {

	function init() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
	}

	function query_vars( $public_qv ) {
		return array_merge( $public_qv, array(
			'connected_type',
			'connected_items',
			'connected_direction',
		) );
	}
}

P2P_URL_Query::init();

