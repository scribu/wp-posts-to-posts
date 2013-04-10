<?php

/**
 * Register a connection type.
 *
 * @link https://github.com/scribu/wp-posts-to-posts/wiki/p2p_register_connection_type
 *
 * @param array $args
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

	if ( isset( $args['prevent_duplicates'] ) ) {
		$args['duplicate_connections'] = !$args['prevent_duplicates'];
	}

	if ( isset( $args['show_ui'] ) ) {
		$args['admin_box'] = array(
			'show' => _p2p_pluck( $args, 'show_ui' )
		);

		if ( isset( $args['context'] ) )
			$args['admin_box']['context'] = _p2p_pluck( $args, 'context' );
	}

	if ( !isset( $args['admin_box'] ) )
		$args['admin_box'] = 'any';

	$ctype = P2P_Connection_Type_Factory::register( $args );

	do_action( 'p2p_registered_connection_type', $ctype, $args );

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
	$args = wp_parse_args( $args, array(
		'direction' => 'from',
		'from' => 'any',
		'to' => 'any',
		'fields' => 'all',
	) );

	$r = array();

	foreach ( _p2p_expand_direction( $args['direction'] ) as $direction ) {
		$dirs = array( $args['from'], $args['to'] );

		if ( 'to' == $direction ) {
			$dirs = array_reverse( $dirs );
		}

		if ( 'object_id' == $args['fields'] )
			$fields = ( 'to' == $direction ) ? 'p2p_from' : 'p2p_to';
		else
			$fields = $args['fields'];

		$r = array_merge( $r, _p2p_get_connections( $p2p_type, array(
			'from' => $dirs[0],
			'to' => $dirs[1],
			'fields' => $fields
		) ) );
	}

	if ( 'count' == $args['fields'] )
		return array_sum( $r );

	return $r;
}

/** @internal */
function _p2p_get_connections( $p2p_type, $args = array() ) {
	global $wpdb;

	$where = $wpdb->prepare( 'WHERE p2p_type = %s', $p2p_type );

	foreach ( array( 'from', 'to' ) as $key ) {
		if ( 'any' == $args[ $key ] )
			continue;

		if ( empty( $args[ $key ] ) )
			return array();

		$value = scbUtil::array_to_sql( _p2p_normalize( $args[ $key ] ) );

		$where .= " AND p2p_$key IN ($value)";
	}

	switch ( $args['fields'] ) {
	case 'p2p_id':
	case 'p2p_from':
	case 'p2p_to':
		$sql_field = $args['fields'];
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

	$args = wp_parse_args( $args, array(
		'direction' => 'from',
		'from' => false,
		'to' => false,
		'meta' => array()
	) );

	list( $from ) = _p2p_normalize( $args['from'] );
	list( $to ) = _p2p_normalize( $args['to'] );

	if ( !$from || !$to )
		return false;

	$dirs = array( $from, $to );

	if ( 'to' == $args['direction'] ) {
		$dirs = array_reverse( $dirs );
	}

	$wpdb->insert( $wpdb->p2p, array(
		'p2p_type' => $p2p_type,
		'p2p_from' => $dirs[0],
		'p2p_to' => $dirs[1]
	) );

	$p2p_id = $wpdb->insert_id;

	foreach ( $args['meta'] as $key => $value )
		p2p_add_meta( $p2p_id, $key, $value );

	do_action( 'p2p_created_connection', $p2p_id );

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

	do_action( 'p2p_delete_connections', $p2p_ids );

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
 * List some items.
 *
 * @param object|array A P2P_List instance, a WP_Query instance, or a list of post objects
 * @param array $args (optional)
 */
function p2p_list_posts( $posts, $args = array() ) {
	if ( is_a( $posts, 'P2P_List' ) ) {
		$list = $posts;
	} else {
		if ( is_a( $posts, 'WP_Query' ) )
			$posts = $posts->posts;

		$list = new P2P_List( $posts, 'P2P_Item_Post' );
	}

	return P2P_List_Renderer::render( $list, $args );
}

/**
 * Given a list of objects and another list of connected items,
 * distribute each connected item to it's respective counterpart.
 *
 * @param array List of objects
 * @param array List of connected objects
 * @param string Name of connected array property
 */
function p2p_distribute_connected( $items, $connected, $prop_name ) {
	$indexed_list = array();

	foreach ( $items as $item ) {
		$item->$prop_name = array();
		$indexed_list[ $item->ID ] = $item;
	}

	$groups = scb_list_group_by( $connected, '_p2p_get_other_id' );

	foreach ( $groups as $outer_item_id => $connected_items ) {
		$indexed_list[ $outer_item_id ]->$prop_name = $connected_items;
	}
}

