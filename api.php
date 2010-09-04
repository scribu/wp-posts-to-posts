<?php

/**
 * Register a connection between two post types.
 * This creates the appropriate meta box in the admin edit screen
 *
 * @param array $args Can be:
 *  'from' string|array The first end of the connection
 *  'to' string|array The second end of the connection
 *  'title' string The box's title
 *  'reciprocal' bool wether to show the box on both sides of the connection
 *  'box' string A class that implements the P2P_Box interface. Default: P2P_Box_Multiple
 */
function p2p_register_connection_type( $args ) {
	$argv = func_get_args();

	if ( count( $argv ) > 1 ) {
		$args = array();
		list( $args['from'], $args['to'] ) = $argv;
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
 */
function p2p_connect( $from, $to, $data = array() ) {
	foreach ( (array) $from as $from ) {
		foreach ( (array) $to as $to ) {
			P2P_Connections::add( $from, $to, $data );
		}
	}
}

/**
 * Disconnect a post from or more other posts
 *
 * @param int|array $from The first end of the connection
 * @param int|array $to The second end of the connection
 */
function p2p_disconnect( $from, $to, $data = array() ) {
	foreach ( (array) $from as $from ) {
		foreach ( (array) $to as $to ) {
			P2P_Connections::delete( $from, $to, $data );
		}
	}
}

/**
 * Get the list of connected posts
 *
 * @param int $post_id One end of the connection
 * @param string $direction The direction of the connection. Can be 'to', 'from' or 'both'
 *
 * @return array A list of post ids
 */
function p2p_get_connected( $post_id, $direction = 'to', $data = array() ) {
	if ( 'both' == $direction ) {
		$to = P2P_Connections::get( $post_id, 'to', $data );
		$from = P2P_Connections::get( $post_id, 'from', $data );
		$ids = array_merge( $to, array_diff( $from, $to ) );
	} else {
		$ids = P2P_Connections::get( $post_id, $direction, $data );
	}

	return $ids;
}

/**
 * See if a certain post is connected to another one
 *
 * @param int $from The first end of the connection
 * @param int $to The second end of the connection
 *
 * @return bool True if the connection exists, false otherwise
 */
function p2p_is_connected( $from, $to, $data = array() ) {
	$ids = p2p_get_connected( $from, $to, $data );

	return !empty( $ids );
}

// Allows you to write query_posts( array( 'connected' => 123 ) );
class P2P_Query {

	function init() {
		add_filter( 'posts_where', array( __CLASS__, 'posts_where' ), 10, 2 );
	}

	function posts_where( $where, $wp_query ) {
		global $wpdb;

		$map = array(
			'connected' => 'both',
			'connected_to' => 'to',
			'connected_from' => 'from',
		);

		foreach ( $map as $qv => $direction ) {
			if ( $id = $wp_query->get( $qv ) ) {
				$where .= " AND $wpdb->posts.ID IN ( " . implode( ',', p2p_get_connected( $id, $direction ) ) . " )";
			}
		}

		return $where;
	}
}

