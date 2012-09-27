<?php

// Tools for testing and debugging P2P

add_action( 'p2p_registered_connection_type', '_p2p_register_missing_post_types' );

function _p2p_register_missing_post_types( $ctype ) {
	foreach ( _p2p_extract_post_types( $ctype->side ) as $ptype ) {
		if ( !post_type_exists( $ptype ) ) {
			_p2p_generate_post_type( $ptype );
		}
	}
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

