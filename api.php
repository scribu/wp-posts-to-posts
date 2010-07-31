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
 * @param int $post_b The second end of the connection
 */
function p2p_connect( $post_a, $post_b ) {
	add_post_meta( $post_a, P2P_META_KEY, $post_b );

	if ( p2p_connection_type_is_reciprocal( get_post_type( $post_a ), get_post_type( $post_b ) ) )
		add_post_meta( $post_b, P2P_META_KEY, $post_a );
}

/**
 * Disconnect a post from another one
 *
 * @param int $post_a The first end of the connection
 * @param int $post_b The second end of the connection
 */
function p2p_disconnect( $post_a, $post_b ) {
	delete_post_meta( $post_a, P2P_META_KEY, $post_b );

	if ( p2p_connection_type_is_reciprocal( get_post_type( $post_a ), get_post_type( $post_b ) ) )
		delete_post_meta( $post_b, P2P_META_KEY, $post_a );
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
	$r = (bool) get_post_meta( $post_b, P2P_META_KEY, $post_a, true );

	if ( p2p_connection_type_is_reciprocal( get_post_type( $post_a ), get_post_type( $post_b ) ) )
		$r = $r && p2p_is_connected( $post_b, $post_a );

	return $r;
}

/**
 * Get the list of connected posts
 *
 * @param string $post_type The post type of the connected posts.
 * @param string $direction The direction of the connection. Can be 'to' or 'from'
 * @param int $post_id One end of the connection
 * @param bool $grouped Wether the results should be grouped by post type
 *
 * @return array[int] if $grouped is True
 * @return array[string => array[int]] if $grouped is False
 */
function p2p_get_connected( $post_type, $direction, $post_id, $grouped = false ) {
	global $wpdb;

	$post_id = absint( $post_id );

	if ( !$post_id || ( 'any' != $post_type && !post_type_exists( $post_type ) ) )
		return false;

	if ( 'to' == $direction ) {
		$col_a = 'post_id';
		$col_b = 'meta_value';
	} else {
		$col_b = 'post_id';
		$col_a = 'meta_value';
	}

	if ( 'any' == $post_type && $grouped ) {
		$query = "
			SELECT $col_a AS post_id, (
				SELECT post_type
				FROM $wpdb->posts
				WHERE $wpdb->posts.ID = $col_a
			) AS type
			FROM $wpdb->postmeta
			WHERE meta_key = '" . P2P_META_KEY . "'
			AND $col_b = $post_id
		";

		$connections = array();
		foreach ( $wpdb->get_results( $query ) as $row )
			$connections[$row->type][] = $row->post_id;

		return $connections;
	}

	$where = "
		WHERE meta_key = '" . P2P_META_KEY . "'
		AND $col_b = $post_id
	";

	if ( 'any' != $post_type )
		$where .= $wpdb->prepare( "
		AND $col_a IN (
			SELECT ID
			FROM $wpdb->posts
			WHERE post_type = %s
		)
		", $post_type );

	$connections = $wpdb->get_col( "
		SELECT $col_a 
		FROM $wpdb->postmeta 
		$where
	" );

	if ( $grouped )
		return array( $post_type => $connections );

	return $connections;
}

/**
 * Display the list of connected posts
 *
 * @param string $post_type The post type of the connected posts.
 * @param string $direction The direction of the connection. Can be 'to' or 'from'
 * @param int $post_id One end of the connection
 * @param callback(WP_Query) $callback the function used to do the actual displaying
 */
function p2p_list_connected( $post_type = 'any', $direction = 'from', $post_id = '', $callback = '' ) {
	if ( !$post_id )
		$post_id = get_the_ID();

	$connected_post_ids = p2p_get_connected( $post_type, $direction, $post_id );

	if ( empty( $connected_post_ids ) )
		return;

	$args = array(
		'post__in' => $connected_post_ids,
		'post_type'=> $post_type,
		'nopaging' => true,
	);
	$query = new WP_Query( $args );

	if ( empty( $callback ) )
		$callback = '_p2p_list_connected';

	call_user_func( $callback, $query );

	wp_reset_postdata();
}

/**
 * The default callback for p2p_list_connected()
 * Lists the posts as an unordered list
 *
 * @param WP_Query
 */
function _p2p_list_connected( $query ) {
	if ( $query->have_posts() ) :
		echo '<ul>';
		while ( $query->have_posts() ) : $query->the_post();
			echo html( 'li', html_link( get_permalink( get_the_ID() ), get_the_title() ) );
		endwhile;
		echo '</ul>';
	endif;
}

