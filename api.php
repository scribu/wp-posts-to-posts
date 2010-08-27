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
 * @param string|array $post_type The post type of the connected posts.
 * @param string $output Can be 'ids' or 'objects'
 *
 * @return array A list of post_ids if $output = 'ids'
 * @return object A WP_Query instance otherwise
 */
function p2p_get_connected( $post_id, $direction = 'to', $post_type = 'any', $output = 'ids' ) {
	if ( 'both' == $direction ) {
		$to = P2P_Storage::get_connected( $post_id, 'to' );
		$from = P2P_Storage::get_connected( $post_id, 'from' );
		$ids = array_merge( $to, array_diff( $from, $to ) );
	} else {
		$ids = P2P_Storage::get_connected( $post_id, $direction );
	}

	if ( empty( $ids ) )
		return array();

	if ( 'any' == $post_type && 'ids' == $output )
		return $ids;

	$args = array(
		'post__in' => $ids,
		'post_type'=> $post_type,
		'post_status' => 'any',
		'nopaging' => true,
	);

	$posts = get_posts( $args );

	if ( 'objects' == $output )
		return $posts;

	foreach ( $posts as &$post )
		$post = $post->ID;

	return $posts;
}

/**
 * Display the list of connected posts as an unordered list
 *
 * @param array $args See p2p_get_connected()
 */
function p2p_list_connected( $post_id, $direction = 'to', $post_type = 'any' ) {
	$posts = p2p_get_connected( $post_id, $direction, $post_type, 'objects' );

	if ( empty( $posts ) )
		return;

	echo '<ul>';
	foreach ( $posts as $post )
		echo html( 'li', html_link( get_permalink( $post->ID ), get_the_title( $post->ID ) ) );
	echo '</ul>';
}

