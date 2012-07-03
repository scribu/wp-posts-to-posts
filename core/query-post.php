<?php

class P2P_WP_Query {

	static function init() {
		add_action( 'parse_query', array( __CLASS__, 'parse_query' ), 20 );
		add_filter( 'posts_clauses', array( __CLASS__, 'posts_clauses' ), 20, 2 );
		add_filter( 'posts_request', array( __CLASS__, 'capture' ), 999, 2 );
		add_filter( 'the_posts', array( __CLASS__, 'cache_p2p_meta' ), 20, 2 );
	}

	static function parse_query( $wp_query ) {
		$p2p_q = P2P_Query::create_from_qv( $wp_query->query_vars, 'post' );

		if ( is_wp_error( $p2p_q ) ) {
			trigger_error( $p2p_q->get_error_message(), E_USER_WARNING );

			$wp_query->set( 'year', 2525 );
			return;
		}

		if ( null === $p2p_q )
			return;

		$wp_query->_p2p_query = $p2p_q;

		$p2p_q->alter_qv( $wp_query->query_vars );

		$wp_query->is_home = false;
		$wp_query->is_archive = true;
	}

	static function posts_clauses( $clauses, $wp_query ) {
		global $wpdb;

		if ( !isset( $wp_query->_p2p_query ) )
			return $clauses;

		return $wp_query->_p2p_query->alter_clauses( $clauses, "$wpdb->posts.ID" );
	}

	static function capture( $request, $wp_query ) {
		global $wpdb;

		if ( !isset( $wp_query->_p2p_capture ) )
			return $request;

		$wp_query->_p2p_sql = $request;

		return '';
	}

	/**
	 * Pre-populates the p2p meta cache to decrease the number of queries.
	 */
	static function cache_p2p_meta( $the_posts, $wp_query ) {
		if ( isset( $wp_query->_p2p_query ) && !empty( $the_posts ) )
			update_meta_cache( 'p2p', wp_list_pluck( $the_posts, 'p2p_id' ) );

		return $the_posts;
	}
}

P2P_WP_Query::init();

