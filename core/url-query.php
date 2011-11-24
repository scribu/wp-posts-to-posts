<?php

class P2P_URL_Query {

	function init() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
	}

	function query_vars( $public_qv ) {
		$public_qv[] = 'connected_type';
		$public_qv[] = 'connected_posts';

		return $public_qv;
	}
}

P2P_URL_Query::init();

