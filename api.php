<?php

function p2p_register_connection_type($post_type_a, $post_type_b, $bydirectional = false) {
	if ( !$ptype = get_post_type_object($post_type_a) )
		return;

	if ( empty($post_type_b) )
		return;

	if ( empty($ptype->can_connect_to) )
		$ptype->can_connect_to = array();

	$post_type_b = (array) $post_type_b;

	$ptype->can_connect_to = array_merge($ptype->can_connect_to, $post_type_b);

	if ( $bydirectional )
		foreach ( $post_type_b as $ptype_b )
			p2p_register_connection_type($ptype_b, $post_type_a, false);
}

function p2p_get_connection_types($post_type_a) {
	return (array) @get_post_type_object($post_type_a)->can_connect_to;
}


function p2p_connect($post_a, $post_b, $bydirectional = false) {
	add_post_meta($post_a, P2P_META_KEY, $post_b, true);

	if ( $bydirectional )
		add_post_meta($post_b, P2P_META_KEY, $post_a, true);
}

function p2p_disconnect($post_a, $post_b, $bydirectional = false) {
	delete_post_meta($post_a, P2P_META_KEY, $post_b);

	if ( $bydirectional )
		delete_post_meta($post_b, P2P_META_KEY, $post_a);
}

function p2p_is_connected($post_a, $post_b, $bydirectional = false) {
	$r = (bool) get_post_meta($post_b, P2P_META_KEY, $post_a, true);

	if ( $bydirectional )
		$r = $r && p2p_is_connected($post_b, $post_a);

	return $r;
}

function p2p_get_connected($post_type, $direction, $post_id) {
	if ( empty($post_type) )
		$post_type = 'any';

	$post_id = absint($post_id);

	if ( !$post_id || ('any' != $post_type && !is_post_type($post_type)) )
		return false;

	if ( 'to' == $direction ) {
		$col_a = 'post_id';
		$col_b = 'meta_value';
	} else {
		$col_b = 'post_id';
		$col_a = 'meta_value';
	}

	global $wpdb;

	if ( 'any' != $post_type ) {
		$query = $wpdb->prepare("
			SELECT $col_a
			FROM $wpdb->postmeta
			WHERE meta_key = '" . P2P_META_KEY . "'
			AND $col_b = $post_id
			AND $col_a IN (
				SELECT ID
				FROM $wpdb->posts
				WHERE post_type = %s
			)
		", $post_type);

		return $wpdb->get_col($query);
	}

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
	foreach ( $wpdb->get_results($query) as $row )
		$connections[$row->type][] = $row->post_id;

	return $connections;
}

