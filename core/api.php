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
 * - 'self_connections' - bool Whether to allow a post to connect to itself. Default: false.
 *
 * - 'sortable' - bool|string Whether to allow connections to be ordered via drag-and-drop. Can be 'from', 'to', 'any' or false. Default: false.
 *
 * - 'title' - string|array The box's title. Default: 'Connected {$post_type}s'
 *
 * - 'from_labels' - array Additional labels for the admin box (optional)
 *
 * - 'to_labels' - array Additional labels for the admin box (optional)
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

	if ( count( $argv ) > 1 ) {
		$args = array();
		foreach ( array( 'from', 'to', 'reciprocal' ) as $i => $key ) {
			if ( isset( $argv[ $i ] ) )
				$args[ $key ] = $argv[ $i ];
		}
	} else {
		$args = $argv[0];
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

	return P2P_Connection_Type_Factory::register( $args );
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
 * Check if a certain connection exists.
 *
 * @param string $p2p_type A valid connection type.
 * @param array $args Query args.
 *
 * @return bool
 */
function p2p_connection_exists( $p2p_type, $args = array() ) {
	$args['fields'] = 'count';

	$r = p2p_get_connections( $p2p_type, $args );

	return (bool) $r;
}

/**
 * Retrieve connections.
 *
 * @param string $p2p_type A valid connection type.
 * @param array $args Query args:
 *
 * - 'direction': Can be 'from', 'to' or 'any'
 * - 'from': Object id. The first end of the connection. (optional)
 * - 'to': Object id. The second end of the connection. (optional)
 * - 'fields': Which field of the connection to return. Can be:
 * 		'all', 'object_id', 'p2p_from', 'p2p_to', 'p2p_id' or 'count'
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

	foreach ( _p2p_expand_direction( $direction ) as $direction ) {
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

	if ( 'count' == $fields )
		return array_sum( $r );

	return $r;
}

/** @internal */
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
	case 'count':
		$sql_field = 'COUNT(*)';
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
		'direction' => 'from',
		'from' => false,
		'to' => false,
		'meta' => array()
	) ), EXTR_SKIP );

	$from = absint( $from );
	$to = absint( $to );

	if ( !$from || !$to )
		return false;

	$args = array( $from, $to );

	if ( 'to' == $direction ) {
		$args = array_reverse( $args );
	}

	$wpdb->insert( $wpdb->p2p, array(
		'p2p_type' => $p2p_type,
		'p2p_from' => $args[0],
		'p2p_to' => $args[1]
	) );

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

	$i = 0;
	
	foreach ( $posts as $post ) {
		$GLOBALS['post'] = $post;

		setup_postdata( $post );

		if ( !isset( $separator ) ) echo $before_item;

		if ( $template )
			locate_template( $template, true, false );
		else
			if ( 0 < $i && isset( $separator ) ) echo $separator;
			
			echo html( 'a', array( 'href' => get_permalink( $post->ID ) ), get_the_title( $post->ID ) );

		if ( !isset( $separator ) ) echo $after_item;
		
		$i++;
	}

	echo $after_list;

	wp_reset_postdata();
}

