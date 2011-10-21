<?php

/**
 * Register a connection between two post types.
 *
 * This creates the appropriate meta box in the admin edit screen.
 *
 * Takes the following parameters, as an associative array:
 *
 * - 'id' - string A unique identifier for this connection type (optional).
 *
 * - 'from' - string|array The first end of the connection.
 *
 * - 'from_query_vars' - array Additional query vars to pass to WP_Query. Default: none.
 *
 * - 'to' - string|array The second end of the connection.
 *
 * - 'to_query_vars' - array Additional query vars to pass to WP_Query. Default: none.
 *
 * - 'fields' - array( key => Title ) Metadata fields editable by the user. Default: none.
 *
 * - 'data' - array( key => value ) Metadata fields not editable by the user. Dfault: none.
 *
 * - 'cardinality' - string How many connection can each post have: 'one-to-many', 'many-to-one' or 'many-to-many'. Default: 'many-to-many'
 *
 * - 'prevent_duplicates' - bool Whether to disallow duplicate connections between the same two posts. Default: true.
 *
 * - 'sortable' - string A custom field key used to add a special column that allows manual connection ordering. Default: false.
 *
 * - 'title' - string The box's title. Default: 'Connected {$post_type}s'
 *
 * - 'reciprocal' - bool Whether to show the box on both sides of the connection. Default: false.
 *
 * - 'show_ui' - bool Whether to show the admin connections box. Default: true.
 *
 * - 'context' - string Where should the box show up by default. Possible values: 'advanced' or 'side'
 *
 * - 'can_create_post' - bool Whether to allow post creation via the connection box. Default: true.
 *
 * @param array $args
 *
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
		'can_create_post' => true
	) );

	return P2P_Connection_Type::register( $args );
}

/**
 * Get a connection type.
 *
 * @param string $id Connection type id
 *
 * @return bool|object False if connection type not found, P2P_Connection_Type instance on success.
 */
function p2p_type( $id ) {
	return P2P_Connection_Type::get( $id );
}

/**
 * Delete one or more connections.
 *
 * @param int|array $p2p_id Connection ids
 *
 * @return int Number of connections deleted
 */
function p2p_delete_connection( $p2p_id ) {
	return P2P_Storage::delete( $p2p_id );
}

/**
 * Split some posts based on a certain connection field.
 *
 * @param object|array A WP_Query instance, or a list of post objects
 * @param string $key p2pmeta key
 */
function p2p_split_posts( $posts, $key ) {
	if ( is_object( $posts ) )
		$posts = $posts->posts;

	$buckets = array();

	foreach ( $posts as $post ) {
		$buckets[ p2p_get_meta( $post->p2p_id, $key, true ) ][] = $post;
	}

	return $buckets;
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

