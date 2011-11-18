<?php

/**
 * Handles connected{_to|_from} query vars
 */
class P2P_Query {

	function init() {
		add_action( 'parse_query', array( __CLASS__, 'parse_legacy_qv' ) );
		add_filter( 'posts_clauses', array( __CLASS__, 'posts_clauses' ), 10, 2 );
		add_filter( 'the_posts', array( __CLASS__, 'cache_p2p_meta' ), 11, 2 );
	}

	function parse_legacy_qv( $wp_query ) {
		$qv_map = array(
			'connected' => 'any',
			'connected_to' => 'to',
			'connected_from' => 'from',
		);

		foreach ( $qv_map as $key => $direction ) {
			$search = $wp_query->get( $key );
			if ( !empty( $search ) ) {
				$wp_query->set( 'connected_query', array(
					'posts' => $search,
					'direction' => $direction,
				) );

				$wp_query->set( $key, false );
			}
		}
	}

	function posts_clauses( $clauses, $wp_query ) {
		global $wpdb;

		$connected_query = $wp_query->get( 'connected_query' );
		if ( !is_array( $connected_query ) ) {
			return $clauses;
		}

		$defaults = array(
			'posts' => 'any',
			'direction' => 'any',
			'operator' => 'in'
		);

		$connected_query = array_merge( $defaults, $connected_query );

		$wp_query->_p2p_cache = true;

		$clauses['fields'] .= ", $wpdb->p2p.*";

		$clauses['join'] .= " INNER JOIN $wpdb->p2p";

		if ( 'any' == $connected_query['posts'] ) {
			$search = false;
		} else {
			$search = implode( ',', array_map( 'absint', (array) $connected_query['posts'] ) );
		}

		$direction = $connected_query['direction'];
		if ( !in_array( $direction, array( 'from', 'to', 'any' ) ) )
			$direction = 'any';

		if ( 'any' == $direction ) {
			if ( $search ) {
				$clauses['where'] .= " AND (
					($wpdb->posts.ID = $wpdb->p2p.p2p_to AND $wpdb->p2p.p2p_from IN ($search)) OR
					($wpdb->posts.ID = $wpdb->p2p.p2p_from AND $wpdb->p2p.p2p_to IN ($search))
				)";
			} else {
				$clauses['where'] .= " AND ($wpdb->posts.ID = $wpdb->p2p.p2p_to OR $wpdb->posts.ID = $wpdb->p2p.p2p_from)";
			}
		} else {
			$fields = array( 'p2p_from', 'p2p_to' );
			if ( 'from' == $direction )
				$fields = array_reverse( $fields );

			list( $from, $to ) = $fields;

			$clauses['where'] .= " AND $wpdb->posts.ID = $wpdb->p2p.$from";
			if ( $search ) {
				$clauses['where'] .= " AND $wpdb->p2p.$to IN ($search)";
			}
		}

		$connected_meta = $wp_query->get( 'connected_meta' );
		if ( !empty( $connected_meta ) ) {
			$meta_clauses = _p2p_meta_sql_helper( $connected_meta );
			foreach ( $meta_clauses as $key => $value ) {
				$clauses[ $key ] .= $value;
			}
		}

		// Handle ordering
		$connected_orderby = $wp_query->get( 'connected_orderby' );
		if ( $connected_orderby ) {
			$clauses['join'] .= $wpdb->prepare( "
				LEFT JOIN $wpdb->p2pmeta AS p2pm_order ON (
					$wpdb->p2p.p2p_id = p2pm_order.p2p_id AND p2pm_order.meta_key = %s
				)
			", $connected_orderby );

			$connected_order = ( 'DESC' == strtoupper( $wp_query->get('connected_order') ) ) ? 'DESC' : 'ASC';

			$field = 'meta_value';

			if ( $wp_query->get('connected_order_num') )
				$field .= '+0';

			$clauses['orderby'] = "p2pm_order.$field $connected_order";
		}

		return $clauses;
	}

	/**
	 * Pre-populates the p2p meta cache to decrease the number of queries.
	 */
	function cache_p2p_meta( $the_posts, $wp_query ) {
		if ( empty( $the_posts ) )
			return $the_posts;

		if ( isset( $wp_query->_p2p_cache ) ) {
			update_meta_cache( 'p2p', wp_list_pluck( $the_posts, 'p2p_id' ) );
		}

		return $the_posts;
	}
}

P2P_Query::init();

