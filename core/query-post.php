<?php

class P2P_Post_Query {

	static function init() {
		add_action( 'parse_query', array( __CLASS__, 'parse_query' ), 20 );
		add_filter( 'posts_clauses', array( __CLASS__, 'posts_clauses' ), 20, 2 );
		add_filter( 'posts_request', array( __CLASS__, 'capture' ), 999, 2 );
		add_filter( 'posts_results', array( __CLASS__, 'posts_results'), 1, 2 );
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

		$wp_query->is_home = false;
		$wp_query->is_archive = true;
	}

	static function posts_clauses( $clauses, $wp_query ) {
		global $wpdb;

		if ( !isset( $wp_query->_p2p_query ) )
			return $clauses;

		// Alter WP clauses for get only ID instead all datas
		$clauses['fields'] = "$wpdb->posts.ID";

		// Add P2P to clauses
		return $wp_query->_p2p_query->alter_clauses( $clauses, "$wpdb->posts.ID" );
	}

	static function capture( $request, $wp_query ) {
		global $wpdb;

		if ( !isset( $wp_query->_p2p_capture ) )
			return $request;

		$wp_query->_p2p_sql = $request;

		return '';
	}

	static function posts_results( $posts, $wp_query ) {
		global $wpdb;
		
		if ( !isset( $wp_query->_p2p_query ) || empty($posts) )
			return $posts;
		
		// Get posts IDs for get contents
		$_posts = array();
		foreach( $posts as $post ) {
			$_posts[] = $post->ID;
		}
		
		// setup posts data
		_prime_post_caches( $_posts, $wp_query->query_vars['update_post_term_cache'], $wp_query->query_vars['update_post_meta_cache'] );
		$_posts = array_map( 'get_post', $_posts );
		
		// Put ID on key
		foreach( $_posts as $key => $_post ) {
			unset($_posts[$key]);
			$_posts[$_post->ID] = $_post;
		}
		
		// Merge datas
		$wp_query->posts = array();
		foreach( $posts as $post ) {
			$wp_query->posts[] = (object) array_merge((array) $_posts[$post->ID], (array) $post);
		}
		
		return $wp_query->posts;
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

P2P_Post_Query::init();

