<?php

class P2P_User_Query {

	function init() {
		add_action( 'pre_user_query', array( __CLASS__, 'pre_user_query' ) );
	}

	function pre_user_query( $query ) {
		$q =& $query->query_vars;

		$directed = P2P_Query::handle_qv( $q );

		if ( null === $directed )
			return;

		if ( false === $directed )
			$q = array( 'include' => array(0) );


	}
}

P2P_User_Query::init();

