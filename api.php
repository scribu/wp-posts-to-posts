<?php

/**
 * Register a connection between two post types.
 * This creates the appropriate meta box in the admin edit screen
 *
 * @param array $args Can be:
 *  - 'from' string|array The first end of the connection.
 *  - 'to' string|array The second end of the connection.
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

	foreach ( (array) $args['from'] as $from ) {
		foreach ( (array) $args['to'] as $to ) {
			P2P_Connection_Types::register( array_merge( $args, compact( 'from', 'to' ) ) );
		}
	}
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

function p2p_each_connected_to( $query, $qv ) {
	return _p2p_each_connected( 'to', $query, $qv );
}

function p2p_each_connected_from( $query, $qv ) {
	return _p2p_each_connected( 'from', $query, $qv );
}

function p2p_each_connected( $query, $qv ) {
	return _p2p_each_connected( 'any', $query, $qv );
}

/**
 * Optimized inner query, after the outer query was executed.
 *
 * Populates each of the outer querie's $post objects with a property containing a list of connected posts
 *
 * @param string $direction The direction of the connection. Can be 'to', 'from' or 'any'
 * @param object $query The outer query.
 * @param string|array $args The query vars for the inner query.
 */
function _p2p_each_connected( $direction, $query, $search ) {
	if ( empty( $query->posts ) )
		return;

	$prop_name = 'connected';

	// re-index by ID
	$posts = array();
	foreach ( $query->posts as $post ) {
		$post->$prop_name = array();
		$posts[ $post->ID ] = $post;
	}

	// ignore other 'connected' query vars for the inner query
	foreach ( array_keys( P2P_Query::$qv_map ) as $qv )
		unset( $search[ $qv ] );

	// ignore pagination
	$search['nopaging'] = true;
	foreach ( array( 'showposts', 'posts_per_page', 'posts_per_archive_page' ) as $disabled_qv ) {
		if ( isset( $search[ $disabled_qv ] ) ) {
			trigger_error( "Can't use '$disabled_qv' in an inner query", E_USER_WARNING );
		}
	}

	$map = array(
		'any' => 'connected',
		'from' => 'connected_to',
		'to' => 'connected_from'
	);

	$search[ $map[ $direction ] ] = array_keys( $posts );
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

// Allows you to write query_posts( array( 'connected' => 123 ) ); etc.
class P2P_Query {

	static $qv_map = array(
		'connected' => 'any',
		'connected_to' => 'to',
		'connected_from' => 'from',
	);

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

		// Handle ordering
		$p2p_orderby = $wp_query->get( 'p2p_orderby' );
		if ( $p2p_orderby ) {
			$clauses['join'] .= $wpdb->prepare( "
				LEFT JOIN $wpdb->p2pmeta ON ($wpdb->p2p.p2p_id = $wpdb->p2pmeta.p2p_id AND $wpdb->p2pmeta.meta_key = %s )
			", $p2p_orderby );

			$p2p_order = ( 'DESC' == strtoupper( $wp_query->get('p2p_order') ) ) ? 'DESC' : 'ASC';

			$field = 'meta_value';

			if ( $wp_query->get('p2p_order_num') )
				$field .= '+0';

			$clauses['orderby'] = "$wpdb->p2pmeta.$field $p2p_order";
		}

		return $clauses;
	}

	/**
	 * Handles each_connected* query vars
	 *
	 * @priority 11
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

		_p2p_each_connected( $direction, $wp_query, $search );

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
scbHooks::add( 'P2P_Query' );

/**
 * List some posts
 *
 * @param object|array A WP_Query instance, a list of post objects or a list of post ids
 */
function p2p_list_posts( $posts ) {
	if ( is_object( $posts ) )
		$posts = $posts->posts;

	if ( empty( $posts ) )
		return;

	if ( is_object( $posts[0] ) )
		$posts = wp_list_pluck( $posts, 'ID' );

	echo '<ul>';
	foreach ( $posts as $post_id ) {
		echo html( 'li', html( 'a', array( 'href' => get_permalink( $post_id ) ), get_the_title( $post_id ) ) );
	}
	echo '</ul>';
}
