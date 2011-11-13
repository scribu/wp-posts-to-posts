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
 * - 'sortable' - bool|string Whether to allow connections to be ordered via drag-and-drop. Can be 'from', 'to', 'any' or false. Default: false.
 *
 * - 'title' - string|array The box's title. Default: 'Connected {$post_type}s'
 *
 * - 'reciprocal' - bool For indeterminate connections: True means all connections are displayed in a single box. False means 'from' connections are shown in one box and 'to' connections are shown in another box. Default: false.
 *
 * - 'show_ui' - bool|string Whether to show the admin connections box. Can be 'from', 'to', 'any' or false. Default: 'any'.
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
		trigger_error( "Connection types should not be registered before the 'init' hook." );
	}

	$argv = func_get_args();

	if ( count( $argv ) > 1 ) {
		$args = array();
		foreach ( array( 'from', 'to', 'reciprocal' ) as $i => $key ) {
			if ( isset( $argv[ $i ] ) )
				$args[ $key ] = $argv[ $i ];
		}
	}

	$metabox_args = array(
		'show_ui' => 'any',
		'fields' => array(),
		'context' => 'side',
		'can_create_post' => true
	);

	foreach ( $metabox_args as $key => &$value ) {
		if ( isset( $args[$key] ) ) {
			$value = $args[$key];
			unset( $args[$key] );
		}
	}
	unset( $value );

	if ( is_admin() && $metabox_args['show_ui'] ) {
		foreach ( $metabox_args['fields'] as &$field_args ) {
			if ( !is_array( $field_args ) )
				$field_args = array( 'title' => $field_args );

			$field_args['type'] = _p2p_get_field_type( $field_args );

			if ( 'checkbox' == $field_args['type'] && !isset( $field_args['values'] ) )
				$field_args['values'] = array( true => ' ' );
		}
		$args['_metabox_args'] = (object) $metabox_args;
	}

	return P2P_Connection_Type::register( $args );
}

/**
 * @internal
 */
function _p2p_get_field_type( $args ) {
	if ( isset( $args['type'] ) )
		return $args['type'];

	if ( isset( $args['values'] ) && is_array( $args['values'] ) )
		return 'select';

	return 'text';
}

/**
 * Get a connection type.
 *
 * @param string $id Connection type id
 *
 * @return bool|object False if connection type not found, P2P_Connection_Type instance on success.
 */
function p2p_type( $id ) {
	return P2P_Connection_Type::get_instance( $id );
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
		$value = p2p_get_meta( $post->p2p_id, $key, true );
		$buckets[ $value ][] = $post;
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

