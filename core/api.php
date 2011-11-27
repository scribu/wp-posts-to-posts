<?php

/**
 * Register a connection between two post types.
 *
 * This creates the appropriate meta box in the admin edit screen.
 *
 * Takes the following parameters, as an associative array:
 *
 * - 'name' - string A unique identifier for this connection type.
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
 * - 'admin_box' - bool|string|array Whether and where to show the admin connections box.
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

	// Back-compat begin
	if ( count( $argv ) > 1 ) {
		$args = array();
		foreach ( array( 'from', 'to', 'reciprocal' ) as $i => $key ) {
			if ( isset( $argv[ $i ] ) )
				$args[ $key ] = $argv[ $i ];
		}
	}

	if ( isset( $args['id'] ) ) {
		$args['name'] = _p2p_pluck( $args, 'id' );
	}

	if ( isset( $args['show_ui'] ) ) {
		$args['admin_box'] = array(
			'show' => _p2p_pluck( $args, 'show_ui' )
		);
		if ( isset( $args['context'] ) )
			$args['admin_box']['context'] = _p2p_pluck( $args, 'context' );
	}
	// Back-compat end

	// Box args
	if ( isset( $args['admin_box'] ) ) {
		$metabox_args = _p2p_pluck( $args, 'admin_box' );
		if ( !is_array( $metabox_args ) )
			$metabox_args = array( 'show' => $metabox_args );
	} else {
		$metabox_args = array();
	}

	foreach ( array( 'fields', 'can_create_post' ) as $key ) {
		if ( isset( $args[ $key ] ) ) {
			$metabox_args[ $key ] = _p2p_pluck( $args, $key );
		}
	}

	// Column args
	if ( isset( $args['admin_column'] ) ) {
		$column_args = _p2p_pluck( $args, 'admin_column' );
	} else {
		$column_args = false;
	}

	$ctype = P2P_Connection_Type_Factory::register( $args );

	if ( is_admin() ) {
		P2P_Box_Factory::register( $ctype->name, $metabox_args );
		P2P_Column_Factory::register( $ctype->name, $column_args );
	}

	return $ctype;
}

/**
 * Get a connection type.
 *
 * @param string $p2p_type
 *
 * @return bool|object False if connection type not found, P2P_Connection_Type instance on success.
 */
function p2p_type( $p2p_type ) {
	return P2P_Connection_Type_Factory::get_instance( $p2p_type );
}

/**
 * Retrieve connections.
 *
 * @param string $p2p_type A valid connection type.
 * @param array $args Query args.
 *
 * @return array
 */
function p2p_get_connections( $p2p_type, $args = array() ) {
	extract( wp_parse_args( $args, array(
		'direction' => 'from',
		'from' => 'any',
		'to' => 'any',
		'fields' => 'all',
	) ), EXTR_SKIP );

	$r = array();

	foreach ( P2P_Util::expand_direction( $direction ) as $direction ) {
		$args = array( $from, $to );

		if ( 'to' == $direction ) {
			$args = array_reverse( $args );
		}

		if ( 'object_id' == $fields )
			$field = ( 'to' == $direction ) ? 'p2p_from' : 'p2p_to';
		else
			$field = $fields;

		$r = array_merge( $r, _p2p_get_connections( $p2p_type, array(
			'from' => $args[0],
			'to' => $args[1],
			'fields' => $field
		) ) );
	}

	return $r;
}

/**
 * @internal
 */
function _p2p_get_connections( $p2p_type, $args = array() ) {
	global $wpdb;

	extract( $args, EXTR_SKIP );

	$where = $wpdb->prepare( 'WHERE p2p_type = %s', $p2p_type );

	foreach ( array( 'from', 'to' ) as $key ) {
		if ( 'any' == $$key )
			continue;

		$where .= $wpdb->prepare( " AND p2p_$key = %d", $$key );
	}

	switch ( $fields ) {
	case 'p2p_id':
	case 'p2p_from':
	case 'p2p_to':
		$sql_field = $fields;
		break;
	default:
		$sql_field = '*';
	}

	$query = "SELECT $sql_field FROM $wpdb->p2p $where";

	if ( '*' == $sql_field )
		return $wpdb->get_results( $query );
	else
		return $wpdb->get_col( $query );
}

/**
 * Retrieve a single connection.
 *
 * @param int $p2p_id The connection id.
 *
 * @return object
 */
function p2p_get_connection( $p2p_id ) {
	global $wpdb;

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->p2p WHERE p2p_id = %d", $p2p_id ) );
}

/**
 * Create a connection.
 *
 * @param int $p2p_type A valid connection type.
 * @param array $args Connection information.
 *
 * @return bool|int False on failure, p2p_id on success.
 */
function p2p_create_connection( $p2p_type, $args ) {
	global $wpdb;

	extract( wp_parse_args( $args, array(
		'from' => false,
		'to' => false,
		'meta' => array()
	) ), EXTR_SKIP );

	$from = absint( $from );
	$to = absint( $to );

	if ( !$from || !$to )
		return false;

	$wpdb->insert( $wpdb->p2p, array( 'p2p_type' => $p2p_type, 'p2p_from' => $from, 'p2p_to' => $to ) );

	$p2p_id = $wpdb->insert_id;

	foreach ( $meta as $key => $value )
		p2p_add_meta( $p2p_id, $key, $value );

	return $p2p_id;
}

/**
 * Delete one or more connections.
 *
 * @param int $p2p_type A valid connection type.
 * @param array $args Connection information.
 *
 * @return int Number of connections deleted
 */
function p2p_delete_connections( $p2p_type, $args = array() ) {
	$args['fields'] = 'p2p_id';

	return p2p_delete_connection( p2p_get_connections( $p2p_type, $args ) );
}

/**
 * Delete connections using p2p_ids.
 *
 * @param int|array $p2p_id Connection ids
 *
 * @return int Number of connections deleted
 */
function p2p_delete_connection( $p2p_id ) {
	global $wpdb;

	if ( empty( $p2p_id ) )
		return 0;

	$p2p_ids = array_map( 'absint', (array) $p2p_id );

	$where = "WHERE p2p_id IN (" . implode( ',', $p2p_ids ) . ")";

	$count = $wpdb->query( "DELETE FROM $wpdb->p2p $where" );
	$wpdb->query( "DELETE FROM $wpdb->p2pmeta $where" );

	return $count;
}

function p2p_get_meta( $p2p_id, $key = '', $single = false ) {
	return get_metadata( 'p2p', $p2p_id, $key, $single );
}

function p2p_update_meta( $p2p_id, $key, $value, $prev_value = '' ) {
	return update_metadata( 'p2p', $p2p_id, $key, $value, $prev_value );
}

function p2p_add_meta( $p2p_id, $key, $value, $unique = false ) {
	return add_metadata( 'p2p', $p2p_id, $key, $value, $unique );
}

function p2p_delete_meta( $p2p_id, $key, $value = '' ) {
	return delete_metadata( 'p2p', $p2p_id, $key, $value );
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

