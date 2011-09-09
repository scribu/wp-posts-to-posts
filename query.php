<?php

/**
 * Handles {each_}connected{_to|_from} query vars
 */
class P2P_Query {

	static $qv_map = array(
		'connected' => 'any',
		'connected_to' => 'to',
		'connected_from' => 'from',
	);

	function init() {
		add_filter( 'posts_clauses', array( 'P2P_Query', 'posts_clauses' ), 10, 2 );
		add_filter( 'the_posts', array( 'P2P_Query', 'the_posts' ), 11, 2 );
	}

	/**
	 * Handles connected* query vars
	 */
	function posts_clauses( $clauses, $wp_query ) {
		global $wpdb;

		$found = self::find_qv( $wp_query );

		if ( !$found )
			return $clauses;

		list( $search, $key, $direction ) = $found;

		$clauses['fields'] .= ", $wpdb->p2p.*";

		$clauses['join'] .= " INNER JOIN $wpdb->p2p";

		if ( 'any' == $search ) {
			$search = false;
		} else {
			$search = implode( ',', array_map( 'absint', (array) $search ) );
		}

		switch ( $direction ) {
			case 'from':
				$clauses['where'] .= " AND $wpdb->posts.ID = $wpdb->p2p.p2p_to";
				if ( $search ) {
					$clauses['where'] .= " AND $wpdb->p2p.p2p_from IN ($search)";
				}
				break;

			case 'to':
				$clauses['where'] .= " AND $wpdb->posts.ID = $wpdb->p2p.p2p_from";
				if ( $search ) {
					$clauses['where'] .= " AND $wpdb->p2p.p2p_to IN ($search)";
				}
				break;

			case 'any':
				if ( $search ) {
					$clauses['where'] .= " AND (
						($wpdb->posts.ID = $wpdb->p2p.p2p_to AND $wpdb->p2p.p2p_from IN ($search)) OR
						($wpdb->posts.ID = $wpdb->p2p.p2p_from AND $wpdb->p2p.p2p_to IN ($search))
					)";
				} else {
					$clauses['where'] .= " AND ($wpdb->posts.ID = $wpdb->p2p.p2p_to OR $wpdb->posts.ID = $wpdb->p2p.p2p_from)";
				}
				break;
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
	 * Handles each_connected* query vars
	 */
	function the_posts( $the_posts, $wp_query ) {
		if ( empty( $the_posts ) )
			return $the_posts;

		if ( self::find_qv( $wp_query ) ) {
			update_meta_cache( 'p2p', wp_list_pluck( $the_posts, 'p2p_id' ) );
		}

		$found = self::find_qv( $wp_query, 'each_' );

		if ( !$found )
			return $the_posts;

		list( $search, $qv, $direction ) = $found;

		self::_each_connected( $direction, $wp_query, $search );

		return $the_posts;
	}

	/**
	 * Optimized inner query, after the outer query was executed.
	 *
	 * Populates each of the outer querie's $post objects with a 'connected' property, containing a list of connected posts
	 *
	 * @param string $direction The direction of the connection. Can be 'to', 'from' or 'any'
	 * @param object $query The outer query.
	 * @param string|array $args The query vars for the inner query.
	 */
	function _each_connected( $direction, $query, $search ) {
		if ( empty( $query->posts ) )
			return;

		$prop_name = 'connected';

		$posts = array();
		foreach ( $query->posts as $post ) {
			$post->$prop_name = array();
			$posts[ $post->ID ] = $post;
		}

		// ignore other 'connected' query vars for the inner query
		foreach ( array_keys( self::$qv_map ) as $qv )
			unset( $search[ $qv ] );

		// inverted map
		$map = array(
			'any' => 'connected',
			'from' => 'connected_to',
			'to' => 'connected_from'
		);

		$search[ $map[ $direction ] ] = array_keys( $posts );

		// ignore pagination
		foreach ( array( 'showposts', 'posts_per_page', 'posts_per_archive_page' ) as $disabled_qv ) {
			if ( isset( $search[ $disabled_qv ] ) ) {
				trigger_error( "Can't use '$disabled_qv' in an inner query", E_USER_WARNING );
			}
		}
		$search['nopaging'] = true;

		$search['ignore_sticky_posts'] = true;

		$q = new WP_Query( $search );

		foreach ( $q->posts as $inner_post ) {
			if ( $inner_post->ID == $inner_post->p2p_from )
				$outer_post_id = $inner_post->p2p_to;
			elseif ( $inner_post->ID == $inner_post->p2p_to )
				$outer_post_id = $inner_post->p2p_from;
			else
				throw new Exception( 'Corrupted data.' );

			if ( $outer_post_id == $inner_post->ID )
				throw new Exception( 'Post connected to itself.' );

			array_push( $posts[ $outer_post_id ]->$prop_name, $inner_post );
		}
	}

	private function find_qv( $wp_query, $prefix = '' ) {
		foreach ( self::$qv_map as $qv => $direction ) {
			$search = $wp_query->get( $prefix . $qv );
			if ( !empty( $search ) )
				break;
		}

		if ( empty( $search ) )
			return false;

		return array( $search, $qv, $direction );
	}
}

P2P_Query::init();

