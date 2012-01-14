<?php

/**
 * Handles connected{_to|_from} query vars
 */
class P2P_WP_Query {

	function init() {
		add_action( 'parse_query', array( __CLASS__, 'parse_query' ) );
		add_filter( 'posts_clauses', array( __CLASS__, 'posts_clauses' ), 10, 2 );
		add_filter( 'the_posts', array( __CLASS__, 'cache_p2p_meta' ), 11, 2 );
	}

	function parse_query( $wp_query ) {
		$q =& $wp_query->query_vars;

		P2P_Query::expand_shortcut_qv( $q );

		if ( !isset( $q['connected_items'] ) )
			return;

		if ( !isset( $q['connected_type'] ) )
			return;

		$r = self::expand_connected_type( $q );

		if ( false === $r ) {
			$q = array( 'year' => 2525 );
		} elseif ( $r ) {
			$wp_query->is_home = false;
			$wp_query->is_archive = true;
		}
	}

	// null means do nothing
	// false means trigger 404
	// true means found valid p2p query vars
	function expand_connected_type( &$q ) {
		if ( !isset( $q['connected_type'] ) )
			return;

		$ctype = p2p_type( _p2p_pluck( $q, 'connected_type' ) );

		if ( !$ctype )
			return false;

		if ( isset( $q['connected_direction'] ) ) {
			$directed = $ctype->set_direction( _p2p_pluck( $q, 'connected_direction' ) );
		} else {
			if ( 'any' == $q['connected_items'] ) {
				$post_type = isset( $q['post_type'] ) ? $q['post_type'] : 'post';
			} else {
				$post_type = $q['connected_items'];
			}

			$directed = P2P_Query::find_direction( $ctype, $post_type, 'post' );
		}

		if ( !$directed ) {
			trigger_error( "Can't determine direction", E_USER_WARNING );
			return false;
		}

		$q = $directed->get_connected_args( $q );

		return true;
	}

	function posts_clauses( $clauses, $wp_query ) {
		global $wpdb;

		$qv = P2P_Query::get_qv( $wp_query->query_vars );

		if ( empty( $qv['items'] ) )
			return $clauses;

		$wp_query->_p2p_cache = true;

		return P2P_Query::alter_clauses( $clauses, $qv, "$wpdb->posts.ID" );
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

P2P_WP_Query::init();

