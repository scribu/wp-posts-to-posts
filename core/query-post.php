<?php

/**
 * Handles connected{_to|_from} query vars
 */
class P2P_WP_Query {

	static function init() {
		add_action( 'parse_query', array( __CLASS__, 'parse_query' ), 20 );
		add_filter( 'posts_clauses', array( __CLASS__, 'posts_clauses' ), 10, 2 );
		add_filter( 'the_posts', array( __CLASS__, 'cache_p2p_meta' ), 20, 2 );
	}

	static function parse_query( $wp_query ) {
		$q =& $wp_query->query_vars;

		P2P_Query::expand_shortcut_qv( $q );

		if ( isset( $q['connected_items'] ) ) {
			$item = $q['connected_items'];
		} else {
			$item = isset( $q['post_type'] ) ? $q['post_type'] : 'post';
		}

		$r = P2P_Query::expand_connected_type( $q, $item, 'post' );

		if ( false === $r ) {
			$q = array( 'year' => 2525 );
		} elseif ( $r ) {
			$wp_query->is_home = false;
			$wp_query->is_archive = true;
		}
	}

	static function posts_clauses( $clauses, $wp_query ) {
		global $wpdb;

		$qv = P2P_Query::get_qv( $wp_query->query_vars );

		if ( !$qv )
			return $clauses;

		$wp_query->_p2p_cache = true;

		return P2P_Query::alter_clauses( $clauses, $qv, "$wpdb->posts.ID" );
	}

	/**
	 * Pre-populates the p2p meta cache to decrease the number of queries.
	 */
	static function cache_p2p_meta( $the_posts, $wp_query ) {
		if ( empty( $the_posts ) )
			return $the_posts;

		if ( isset( $wp_query->_p2p_cache ) ) {
			update_meta_cache( 'p2p', wp_list_pluck( $the_posts, 'p2p_id' ) );
		}

		return $the_posts;
	}
}

P2P_WP_Query::init();

