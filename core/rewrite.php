<?php

class P2P_Rewrite {

	function init() {
		add_filter( 'p2p_connection_type_args', array( __CLASS__, 'filter_ctypes' ), 10, 2 );
	}

	function filter_ctypes( $args, $sides ) {
		foreach ( array( 'from', 'to' ) as $key ) {
			if ( !isset( $args[ $key . '_rewrite' ] ) )
				continue;

			$endpoint = $args[ $key . '_rewrite' ];

			if ( true === $endpoint )
				$endpoint = 'connected';

			$sides[ $key ]->add_endpoint( $endpoint );
		}

		return $args;
	}
}

