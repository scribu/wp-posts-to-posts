<?php

/**
 * Register a connection between two post types.
 * This creates the appropriate meta box in the admin edit screen
 *
 * @param array $args Can be:
 *  - 'from' string|array The first end of the connection
 *  - 'to' string|array The second end of the connection
 *  - 'title' string The box's title
 *  - 'reciprocal' bool wether to show the box on both sides of the connection
 *  - 'box' string A class that handles displaying and saving connections. Default: P2P_Box_Multiple
 */
function p2p_register_connection_type( $args ) {
	$argv = func_get_args();

	if ( count( $argv ) > 1 ) {
		$args = array();
		list( $args['from'], $args['to'], $args['reciprocal'] ) = $argv;
	}

	foreach ( (array) $args['from'] as $from ) {
		foreach ( (array) $args['to'] as $to ) {
			$args['from'] = $from;
			$args['to'] = $to;
			P2P_Connection_Types::register( $args );
		}
	}
}

/**
 * Connect a post to one or more other posts
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
 * Disconnect a post from or more other posts
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
 * Get a list of connected posts
 *
 * @param int $post_id One end of the connection
 * @param string $direction The direction of the connection. Can be 'to', 'from' or 'both'
 * @param array $data additional data about the connection to filter against
 *
 * @return array( p2p_id => post_id )
 */
function p2p_get_connected( $post_id, $direction = 'both', $data = array() ) {
	if ( in_array( $direction, array( 'to', 'from' ) ) ) {
		$ids = P2P_Connections::get( $post_id, $direction, $data );
	} else {
		$to = P2P_Connections::get( $post_id, 'to', $data );
		$from = P2P_Connections::get( $post_id, 'from', $data );
		$ids = array_merge( $to, array_diff( $from, $to ) );
	}

	return $ids;
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

// Allows you to write query_posts( array( 'connected' => 123 ) );
class P2P_Query {

	function init() {
		new scbQueryManipulation( array( __CLASS__, 'query' ), false );
	}

	function query( $clauses, $wp_query ) {
		global $wpdb;

		$map = array(
			'connected' => 'both',
			'connected_to' => 'to',
			'connected_from' => 'from',
		);

		foreach ( $map as $qv => $direction ) {
			$search = $wp_query->get( $qv );
			if ( !empty($search) )
				break;
		}

		if ( empty( $search ) )
			return $clauses;

		$clauses['fields'] .= ", $wpdb->p2p.p2p_id";

		$groupby = "{$wpdb->posts}.ID";
		if ( false === strpos( $clauses['groupby'], $groupby ) ) {
			if ( empty( $clauses['groupby'] ) )
				$clauses['groupby'] = $groupby;
			else
				$clauses['groupby'] .= ",$groupby";
		}

		switch ( $direction ) {
			case 'from':
				$clauses['join'] .= " INNER JOIN $wpdb->p2p ON ($wpdb->posts.ID = $wpdb->p2p.p2p_to)";
				break;
			case 'to':
				$clauses['join'] .= " INNER JOIN $wpdb->p2p ON ($wpdb->posts.ID = $wpdb->p2p.p2p_from)";
				break;
			case 'both':
				$clauses['join'] .= " INNER JOIN $wpdb->p2p ON ($wpdb->posts.ID = $wpdb->p2p.p2p_to OR $wpdb->posts.ID = $wpdb->p2p.p2p_from)";
				break;
		}

		if ( 'any' != $search ) {
			$search = absint( $search );

			switch ( $direction ) {
				case 'from':
					$clauses['where'] .= " AND $wpdb->p2p.p2p_from = $search";
					break;
				case 'to':
					$clauses['where'] .= " AND $wpdb->p2p.p2p_to = $search";
					break;
				case 'both':
					$clauses['where'] .= " AND ($wpdb->p2p.p2p_to = $search OR $wpdb->p2p.p2p_from = $search)";
					break;
			}
		}

		$meta_query = $wp_query->get('connected_meta');

		if ( !empty( $meta_query ) ) {
			$meta_clauses = p2p_get_meta_sql( $meta_query, 'p2p', $wpdb->p2p, 'p2p_id' );
			foreach ( $meta_clauses as $key => $value ) {
				$clauses[ $key ] .= $value;
			}
		}

		return $clauses;
	}
}

