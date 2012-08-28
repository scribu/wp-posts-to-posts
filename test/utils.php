<?php

// Tools for testing and debugging P2P

add_filter( 'p2p_connection_type_args', '_p2p_register_missing_post_types' );


function _p2p_register_missing_post_types( $connection_type ) {
	foreach ( array( 'from', 'to' ) as $direction ) {
		if ( 'post' == $connection_type[ $direction . '_object' ] ) {
			foreach ( $connection_type[ $direction . '_query_vars' ]['post_type'] as $ptype ) {
				if ( !post_type_exists( $ptype ) ) {
					_p2p_generate_post_type( $ptype );
				}
			}
		}
	}

	return $connection_type;
}

function _p2p_generate_post_type( $slug ) {
	register_post_type( $slug, array(
		'labels' => array(
			'name' => ucfirst( $slug ),
			'singular_name' => ucfirst( $slug ),
		),
		'public' => true,
		'supports' => array( 'title' )
	) );
}

function _p2p_generate_posts( $type, $count = 20 ) {
	global $wpdb;

	$counts = wp_count_posts( $type );
	$total = $counts->publish;

	$posts = array();

	$title = get_post_type_object( $type )->labels->singular_name;

	for ( $i = $total; $i < $total + $count; $i++ ) {
		$posts[] = get_post( wp_insert_post( array(
			'post_type' => $type,
			'post_title' => $title . ' ' . $i,
			'post_status' => 'publish'
		) ) );
	}

	return $posts;
}

function _p2p_generate_post( $type = 'post' ) {
	$posts = _p2p_generate_posts( $type, 1 );
	return $posts[0];
}

function _p2p_generate_users( $count = 20 ) {
	global $wpdb;

	$total = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->users" );

	$users = array();

	for ( $i = $total; $i < $total + $count; $i++ ) {
		$users[] = new WP_User( wp_insert_user( array(
			'user_login' => 'user_' . $total,
			'user_pass' => '',
		) ) );
	}

	return $users;
}

function _p2p_generate_user() {
	$users = _p2p_generate_users( 1 );
	return $users[0];
}

function _p2p_walk( $posts, $level = 0 ) {
	if ( 0 == $level )
		echo "<pre>\n";

	foreach ( $posts as $post ) {
		echo str_repeat( "\t", $level ) . "$post->ID: $post->post_title\n";

		if ( isset( $post->connected ) )
			_p2p_walk( $post->connected, $level+1 );
	}

	if ( 0 == $level )
		echo "</pre>\n";
}

