<?php

/**
 * Register a connection between two post types.
 * This creates the appropriate meta box in the admin edit screen
 *
 * @param string $post_type_a The first end of the connection
 * @param string|array $post_type_b The second end of the connection
 * @param bool $reciprocal Wether the connection should be reciprocal
 */
function p2p_register_connection_type( $post_type_a, $post_type_b, $reciprocal = false ) {
	if ( !$ptype = get_post_type_object( $post_type_a ) )
		return;

	if ( empty( $post_type_b ) )
		return;

	if ( empty( $ptype->can_connect_to ) )
		$ptype->can_connect_to = array();

	$post_type_b = (array) $post_type_b;

	$ptype->can_connect_to = array_merge( $ptype->can_connect_to, $post_type_b );

	if ( $reciprocal )
		foreach ( $post_type_b as $ptype_b )
			p2p_register_connection_type( $ptype_b, $post_type_a, false );
}

/**
 * Get the registered connection types for a certain post type
 *
 * @param string $post_type_a The first end of the connection
 *
 * @return array[string] A list of post types
 */
function p2p_get_connection_types( $post_type_a ) {
	return (array) @get_post_type_object( $post_type_a )->can_connect_to;
}

/**
 * Check wether a connection type is reciprocal
 *
 * @param string $post_type_a The first end of the connection
 * @param string $post_type_b The second end of the connection
 *
 * @return bool
 */
function p2p_connection_type_is_reciprocal( $post_type_a, $post_type_b ) {
	return
		in_array( $post_type_b, p2p_get_connection_types( $post_type_a ) ) &&
		in_array( $post_type_a, p2p_get_connection_types( $post_type_b ) );
}

/**
 * Connect a post to another one
 *
 * @param int $post_a The first end of the connection
 * @param int|array $post_b The second end of the connection
 * @param bool $reciprocal Wether the connection is reciprocal or not
 */
function p2p_connect( $post_a, $post_b, $reciprocal = false ) {
	Posts2Posts::connect( $post_a, $post_b );

	if ( $reciprocal )
		foreach ( $post_b as $single )
			Posts2Posts::connect( $single, $post_a );
}

/**
 * Disconnect a post from another one
 *
 * @param int $post_a The first end of the connection
 * @param int|array $post_b The second end of the connection
 * @param bool $reciprocal Wether the connection is reciprocal or not
 */
function p2p_disconnect( $post_a, $post_b, $reciprocal = false ) {
	Posts2Posts::disconnect( $post_a, $post_b );

	if ( $reciprocal )
		foreach ( $post_b as $single )
			Posts2Posts::disconnect( $single, $post_a );
}

/**
 * See if a certain post is connected to another one
 *
 * @param int $post_a The first end of the connection
 * @param int $post_b The second end of the connection
 *
 * @return bool True if the connection exists, false otherwise
 */
function p2p_is_connected( $post_a, $post_b, $reciprocal = false ) {
	$r = Posts2Posts::is_connected( $post_a, $post_b );

	if ( $reciprocal )
		$r = $r && Posts2Posts::is_connected( $post_b, $post_a );

	return $r;
}

/**
 * Get the list of connected posts
 *
 * @param int $post_id One end of the connection
 * @param string $direction The direction of the connection. Can be 'to' or 'from'
 * @param string|array $post_type The post type of the connected posts.
 * @param string $output Can be 'ids' or 'objects'
 *
 * @return array A list of post_ids if $output = 'ids'
 * @return object A WP_Query instance otherwise
 */
function p2p_get_connected( $post_id, $direction = 'to', $post_type = 'any', $output = 'ids' ) {
	$ids = Posts2Posts::get_connected( $post_id, $direction );

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

