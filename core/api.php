<?php

/**
 * Register a connection between two post types.
 * This creates the appropriate meta box in the admin edit screen
 *
 * @param array $args See https://github.com/scribu/wp-posts-to-posts/wiki/p2p_register_connection_type()
 * @return bool|object False on failure, P2P_Connection_Type instance on success.
 */
function p2p_register_connection_type( $args ) {
	if ( !did_action('init') ) {
		trigger_error( "Connection types should not be registered before the 'init' hook.", E_USER_NOTICE );
	}

	$argv = func_get_args();

	if ( count( $argv ) > 1 ) {
		$args = array();
		@list( $args['from'], $args['to'], $args['reciprocal'] ) = $argv;
	}

	$args = wp_parse_args( $args, array(
		'show_ui' => true,
		'fields' => array(),
		'context' => 'side',
	) );

	return P2P_Connection_Type::register( $args );
}

/**
 * Get a connection type
 *
 * @param string $id Connection type id
 *
 * @return bool|object False if connection type not found, P2P_Connection_Type instance on success.
 */
function p2p_type( $id ) {
	return P2P_Connection_Type::get( $id );
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
			P2P_Storage::connect( $from, $to, $data );
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
			P2P_Storage::disconnect( $from, $to, $data );
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
	return P2P_Storage::get( $post_id, $direction, $data );
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
	return P2P_Storage::delete( $p2p_id );
}

/**
 * List some posts.
 *
 * @param object|array A WP_Query instance, or a list of post objects
 * @param array $args (optional)
 */
function p2p_list_posts( $posts, $args = array() ) {
	if ( is_object( $posts ) )
		$posts = $posts->posts;

	$args = wp_parse_args( $args, array(
		'before_list' => '<ul>', 'after_list' => '</ul>',
		'before_item' => '<li>', 'after_item' => '</li>',
		'template' => false
	) );

	extract( $args, EXTR_SKIP );

	if ( empty( $posts ) )
		return;

	echo $before_list;

	foreach ( $posts as $post ) {
		$GLOBALS['post'] = $post;

		setup_postdata( $post );

		echo $before_item;

		if ( $template )
			locate_template( $template, true, false );
		else
			echo html( 'a', array( 'href' => get_permalink( $post->ID ) ), get_the_title( $post->ID ) );

		echo $after_item;
	}

	echo $after_list;

	wp_reset_postdata();
}

