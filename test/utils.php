<?php

// Tools for testing and debugging P2P

add_filter( 'p2p_connection_type_args', '_p2p_register_missing_post_types', 10, 2 );


function _p2p_register_missing_post_types( $args, $sides ) {
	foreach ( array( 'from', 'to' ) as $direction ) {
		if ( 'post' == $sides[ $direction ]->get_object_type() ) {
			foreach ( $sides[ $direction ]->query_vars['post_type'] as $ptype ) {
				if ( !post_type_exists( $ptype ) ) {
					_p2p_generate_post_type( $ptype );
				}
			}
		}
	}

	return $args;
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

