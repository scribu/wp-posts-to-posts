<?php

/**
 * Register a connection between two post types.
 * This creates the appropriate meta box in the admin edit screen
 *
 * @param array $args Can be:
 *  - 'from' string|array The first end of the connection.
 *  - 'to' string|array The second end of the connection.
 *  - 'fields' array( key => Title ) Metadata fields editable by the user (optional).
 *  - 'data' array( key => value ) Metadata fields not editable by the user (optional).
 *  - 'sortable' string A custom field key used to add a special column that allows manual connection ordering. Default: false.
 *  - 'prevent_duplicates' bool Wether to disallow duplicate connections between the same two posts. Default: true.
 *  - 'reciprocal' bool Wether to show the box on both sides of the connection. Default: false.
 *  - 'title' string The box's title. Default: 'Connected {$post_type}s'
 *  - 'context' string Where should the box show up by default. Possible values: 'advanced' or 'side'
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
		'reciprocal' => false,

		'fields' => array(),
		'data' => array(),
		'sortable' => false,
		'prevent_duplicates' => true,

		'title' => '',
		'context' => 'side',
	);

	$args = wp_parse_args( $args, $defaults );

	foreach ( (array) $args['from'] as $from ) {
		foreach ( (array) $args['to'] as $to ) {
			$GLOBALS['_p2p_connection_types'][] = array_merge( $args, compact( 'from', 'to' ) );
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

/**
 * Optimized inner query, after the outer query was executed. (any direction)
 *
 * @param object $query The outer query.
 * @param string|array $args The query vars for the inner query.
 */
function p2p_each_connected( $query, $qv ) {
	return P2P_Query::_each_connected( 'any', $query, $qv );
}

/**
 * Optimized inner query, after the outer query was executed. ('to' direction)
 *
 * @param object $query The outer query.
 * @param string|array $args The query vars for the inner query.
 */
function p2p_each_connected_to( $query, $qv ) {
	return P2P_Query::_each_connected( 'to', $query, $qv );
}

/**
 * Optimized inner query, after the outer query was executed. ('from' direction)
 *
 * @param object $query The outer query.
 * @param string|array $args The query vars for the inner query.
 */
function p2p_each_connected_from( $query, $qv ) {
	return P2P_Query::_each_connected( 'from', $query, $qv );
}

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

