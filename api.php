<?php

/**
 * Register a connection between two post types.
 * This creates the appropriate meta box in the admin edit screen
 *
 * @param array $args Can be:
 *  'from' string|array The first end of the connection
 *  'to' string|array The second end of the connection
 *  'title' string The box's title
 */
function p2p_register_connection_type( $args ) {
	$argv = func_get_args();

	if ( count( $argv ) > 1 ) {
		$args = array();
		list( $args['from'], $args['to'] ) = $argv;
	}

	P2P_Connection_Types::register( $args );
}

/**
 * Connect a post to another one
 *
 * @param int $post_a The first end of the connection
 * @param int|array $post_b The second end of the connection
 */
function p2p_connect( $post_a, $post_b ) {
	P2P_Storage::connect( $post_a, $post_b );
}

/**
 * Disconnect a post from another one
 *
 * @param int $post_a The first end of the connection
 * @param int|array $post_b The second end of the connection
 */
function p2p_disconnect( $post_a, $post_b ) {
	P2P_Storage::disconnect( $post_a, $post_b );
}

/**
 * See if a certain post is connected to another one
 *
 * @param int $post_a The first end of the connection
 * @param int $post_b The second end of the connection
 *
 * @return bool True if the connection exists, false otherwise
 */
function p2p_is_connected( $post_a, $post_b ) {
	return P2P_Storage::is_connected( $post_a, $post_b );
}

/**
 * Get the list of connected posts
 *
 * @param int $post_id One end of the connection
 * @param string $direction The direction of the connection. Can be 'to', 'from' or 'both'
 *
 * @return array A list of post ids
 */
function p2p_get_connected( $post_id, $direction = 'to' ) {
	if ( 'both' == $direction ) {
		$to = P2P_Storage::get_connected( $post_id, 'to' );
		$from = P2P_Storage::get_connected( $post_id, 'from' );
		$ids = array_merge( $to, array_diff( $from, $to ) );
	} else {
		$ids = P2P_Storage::get_connected( $post_id, $direction );
	}

	return $ids;
}


// Allows you to write query_posts( array( 'connected' => 123 ) );
class P2P_Query {

	function init() {
		add_filter( 'posts_where', array( __CLASS__, 'posts_where' ), 10, 2 );
	}

	function posts_where( $where, $wp_query ) {
		global $wpdb;

		$map = array(
			'connected' => 'any',
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

