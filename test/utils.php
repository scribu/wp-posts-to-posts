<?php

// Tools for testing and debugging P2P

add_filter( 'p2p_connection_type_args', '_p2p_register_missing_post_types' );


function _p2p_register_missing_post_types( $connection_type ) {
	foreach ( array( 'from', 'to' ) as $direction ) {
		if ( 'post' == $connection_type[ $direction . '_object' ] ) {
			foreach ( $connection_type[ $direction . '_query_vars' ]['post_type'] as $ptype ) {
				if ( !post_type_exists( $ptype ) ) {
					_p2p_quick_post_type( $ptype );
				}
			}
		}
	}

	return $connection_type;
}

function _p2p_quick_post_type( $slug ) {
	register_post_type( $slug, array(
		'label' => ucfirst( $slug ),
		'public' => true,
		'supports' => array( 'title' )
	) );
}

function _p2p_quick_post( $type, $title ) {
	return wp_insert_post( array(
		'post_type' => $type,
		'post_title' => $title,
		'post_status' => 'publish'
	) );
}

function _p2p_walk( $posts, $level = 0 ) {
	if ( !isset( $_GET['p2p_debug'] ) )
		return;

	if ( 0 == $level )
		echo "<pre>\n";

	foreach ( $posts as $post ) {
		echo str_repeat( "\t", $level ) . "$post->ID: $post->post_title\n";

		_p2p_walk( (array) @$post->connected, $level+1 );
	}

	if ( 0 == $level )
		echo "</pre>\n";
}

