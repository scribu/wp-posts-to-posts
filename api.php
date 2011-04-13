<?php

/**
 * Register a connection between two post types.
 * This creates the appropriate meta box in the admin edit screen
 *
 * @param array $args Can be:
 *  - 'from' string The first end of the connection.
 *  - 'to' string The second end of the connection.
 *  - 'fields' array Additional metadata fields (optional).
 *  - 'prevent_duplicates' bool Wether to disallow duplicate connections between the same two posts. Default: true.
 *  - 'reciprocal' bool Wether to show the box on both sides of the connection. Default: false.
 *  - 'title' string The box's title. Default: 'Connected {$post_type}s'
 *  - 'context' string Where should the box show up by default. Possible values: 'advanced' or 'side'
 *  - 'box' string A class that handles displaying and saving connections. Default: P2P_Box_Multiple
 */
function p2p_register_connection_type( $args ) {
	$argv = func_get_args();

	if ( count( $argv ) > 1 ) {
		$args = array();
		@list( $args['from'], $args['to'], $args['reciprocal'] ) = $argv;
	}

	$defaults = array(
		'from' => '',
		'to' => '',
		'fields' => array(),
		'prevent_duplicates' => true,
		'reciprocal' => false,
		'title' => '',
		'context' => 'side',
		'box' => 'P2P_Box_Multiple',
	);

	$args = wp_parse_args( $args, $defaults );

	P2P_Connection_Types::register( $args );
}

/**
 * Connect a post to one or more other posts.
 *
 * @param int|array $from The first end of the connection
 * @param int|array $to The second end of the connection
 * @param array $data additional data about the connection
 */
function p2p_connect( $from, $to, $data = array() ) {
	foreach ( (array) $from as $from ) {
		foreach ( (array) $to as $to ) {
			P2P_Connections::connect( $from, $to, $data );
		}
	}
}

/**
 * Disconnect a post from or more other posts.
 *
 * @param int|array $from The first end of the connection
 * @param int|array|string $to The second end of the connection
 * @param array $data additional data about the connection to filter against
 */
function p2p_disconnect( $from, $to, $data = array() ) {
	foreach ( (array) $from as $from ) {
		foreach ( (array) $to as $to ) {
			P2P_Connections::disconnect( $from, $to, $data );
		}
	}
}

/**
 * Get a list of connected posts.
 *
 * Low-level function. Use new WP_Query( array( 'connected' => $post_id ) ) instead.
 *
 * @param int $post_id One end of the connection
 * @param string $direction The direction of the connection. Can be 'to', 'from' or 'any'
 * @param array $data additional data about the connection to filter against
 *
 * @return array( p2p_id => post_id )
 */
function p2p_get_connected( $post_id, $direction = 'any', $data = array() ) {
	return P2P_Connections::get( $post_id, $direction, $data );
}

/**
 * See if a certain post is connected to another one
 *
 * @param int $from The first end of the connection
 * @param int $to The second end of the connection
 * @param array $data additional data about the connection to filter against
 *
 * @return bool True if the connection exists, false otherwise
 */
function p2p_is_connected( $from, $to, $data = array() ) {
	$ids = p2p_get_connected( $from, $to, $data );

	return !empty( $ids );
}

/**
 * Delete one or more connections
 *
 * @param int|array $p2p_id Connection ids
 *
 * @return int Number of connections deleted
 */
function p2p_delete_connection( $p2p_id ) {
	return P2P_Connections::delete( $p2p_id );
}

/**
 * Optimized inner query, after the outer query was executed.
 *
 * Populates each of the outer querie's $post objects with a property containing a list of connected posts
 *
 * @param string $direction The direction of the connection. Can be 'to', 'from' or 'any'
 * @param string $prop_name The property name; will be prefixed with 'connected_'
 * @param string|array $args The query vars for the inner query
 * @param object $query (optional) The outer query. Defaults to the global $wp_query
 */
function p2p_each_connected( $direction, $prop_name, $search, $query = null ) {
	if ( is_null( $query ) )
		$query = $GLOBALS['wp_query'];

	if ( empty( $query->posts ) )
		return;

	if ( empty( $prop_name ) )
		$prop_name = 'connected';
	else
		$prop_name = 'connected_' . $prop_name;

	// re-index by ID
	$posts = array();
	foreach ( $query->posts as $post ) {
		$post->$prop_name = array();
		$posts[ $post->ID ] = $post;
	}

	// ignore other 'connected' query vars for the inner query
	foreach ( array_keys( P2P_Query::$qv_map ) as $qv )
		unset( $search[ $qv ] );

	if ( 'any' == $direction )
		$key = 'connected';
	else
		$key = 'connected_' . $direction;

	$search[ $key ] = array_keys( $posts );
	$search[ 'suppress_filters' ] = false;

	foreach ( get_posts( $search ) as $inner_post ) {
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

// Allows you to write query_posts( array( 'connected' => 123 ) );
class P2P_Query {
	static $qv_map = array(
		'connected' => 'any',
		'connected_to' => 'to',
		'connected_from' => 'from',
	);

	function init() {
		new scbQueryManipulation( array( __CLASS__, 'query' ), false );		// 'posts_clauses'
		add_filter( 'the_posts', array( __CLASS__, 'the_posts' ), 11, 2 );
	}

	function query( $clauses, $wp_query ) {
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
						($wpdb->posts.ID = $wpdb->p2p.p2p_to AND $wpdb->p2p.p2p_from IN ($search) ) OR
						($wpdb->posts.ID = $wpdb->p2p.p2p_from AND $wpdb->p2p.p2p_to IN ($search) )
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

		return $clauses;
	}

	function the_posts( $the_posts, $wp_query ) {
		if ( empty( $the_posts ) )
			return $the_posts;

		$found = self::find_qv( $wp_query, 'each_' );

		if ( !$found )
			return $the_posts;

		list( $search, $qv, $direction ) = $found;		

		$qv = explode('_', $qv);
		$key = isset( $qv[1] ) ? $qv[1] : '';

		p2p_each_connected( $direction, $key, $search, $wp_query );

		return $the_posts;
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

