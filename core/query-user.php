<?php

class P2P_Query_User {

	static function init() {
		add_action( 'pre_user_query', array( __CLASS__, 'pre_user_query' ), 20 );
	}

	static function pre_user_query( $query ) {
		global $wpdb;

		$r = P2P_Query::create_from_qv( $query->query_vars, 'user' );

		if ( is_wp_error( $r ) ) {
			$query->_p2p_error = $r;

			$query->query_where = " AND 1=0";
			return;
		}

		if ( null === $r )
			return;

		list( $p2p_q, $query->query_vars ) = $r;

		$map = array(
			'fields' => 'query_fields',
			'join' => 'query_from',
			'where' => 'query_where',
			'orderby' => 'query_orderby',
		);

		$clauses = array();

		foreach ( $map as $clause => $key )
			$clauses[$clause] = $query->$key;

		$clauses = $p2p_q->alter_clauses( $clauses, "$wpdb->users.ID" );

		if ( 0 !== strpos( $clauses['orderby'], 'ORDER BY ' ) )
			$clauses['orderby'] = 'ORDER BY ' . $clauses['orderby'];

		foreach ( $map as $clause => $key )
			$query->$key = $clauses[ $clause ];
	}
}

