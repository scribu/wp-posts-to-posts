<?php

class P2P_Debug {

	function init() {
		if ( !function_exists('p2p_register_connection_type') )
			return;

		add_action('init', array(__CLASS__, '_init'));
	}

	function _init() {
		self::contacts_and_tickets();
		self::actors_and_movies();
	}

	function contacts_and_tickets() {
		register_post_type( 'contact', array( 'label' => 'Contacts', 'public' => true ) );
		register_post_type( 'ticket', array( 'label' => 'Tickets', 'public' => true ) );

		$types = array(
			'bug' => 'Bug',
			'feature' => 'Feature'
		);

		foreach ( $types as $type => $title ) {
			p2p_register_connection_type( array(
				'from' => 'contact',
				'to' => 'ticket',
				'to_query_vars' => array(
					'meta_key' => 'type',
					'meta_value' => $type
				),
				'title' => array( 'from' => $title ),
			) );
		}
	}

	function actors_and_movies() {
		register_post_type('actor', array(
			'public' => true,
			'labels' => array(
				'name' => 'Actors',
				'singular_name' => 'Actor',
				'search_items' => 'Search Actors',
				'new_item' => 'New Actor',
				'not_found' => 'No actors found.'
			),
			'has_archive' => 'actors',
			'taxonomies' => array( 'category' )
		));

		register_post_type('movie', array(
			'public' => true,
			'labels' => array(
				'name' => 'Movies',
				'singular_name' => 'Movie',
				'search_items' => 'Search movies',
				'new_item' => 'New Movie',
				'not_found' => 'No movies found.',
			)
		) );


		p2p_register_connection_type( array(
			'id' => 'actor_movie',
			'from' => 'actor',
			'to' => 'movie',
			'fields' => array(
				'role' => 'Role',
				'role_type' => array( 'title' => 'Role Type', 'values' => array( 'lead', 'secondary', 'episodic' ) ),
			),
			'cardinality' => 'one-to-many',
			'sortable' => 'order',
			'prevent_duplicates' => false,
			'context' => 'advanced',
			'reciprocal' => true,
			'title' => array(
				'from' => 'Played In',
				'to' => 'Cast'
			)
		) );

		p2p_register_connection_type( array(
			'from' => 'actor',
			'to' => 'actor',
			'reciprocal' => true,
			'title' => array( 'from' => 'Doubles', 'to' => 'Main Actor' ),
			'data' => array( 'type' => 'doubles' ),
			'sortable' => 'order',
			'can_create_post' => false
		) );

		p2p_register_connection_type( array(
			'from' => array( 'foo', 'actor' ),
			'to' => 'actor',
			'reciprocal' => true,
			'title' => 'Friends with',
			'data' => array( 'type' => 'friends' )
		) );

		p2p_register_connection_type( array( 'actor', 'post' ), array( 'page', 'movie' ), true );
	}

	function test_ordering() {
		$query = new WP_Query( array(
			'connected' => $actor_ids[0],
			'post_type' => 'movie',
			'post_status' => 'any',
			'nopaging' => true,

			'connected_meta' => array( 'foo' => 'bar' ),

			'connected_orderby' => 'role',
			'connected_order_num' => true,
			'connected_order' => 'asc'
		) );

		self::walk( $posts );
	}

	private function walk( $posts, $level = 0 ) {
		if ( !isset( $_GET['p2p_debug'] ) )
			return;

		if ( 0 == $level )
			echo "<pre>\n";

		foreach ( $posts as $post ) {
			echo str_repeat( "\t", $level ) . "$post->ID: $post->post_title\n";

			self::walk( (array) @$post->connected, $level+1 );
		}

		if ( 0 == $level )
			echo "</pre>\n";
	}

	function debug() {
		global $wpdb;

		$rows = $wpdb->get_results("SELECT * FROM $wpdb->p2p");

		foreach ( $rows as $row ) {
			echo html_link( get_edit_post_link( $row->p2p_from ), $row->p2p_from ) . ' -> ';
			echo html_link( get_edit_post_link( $row->p2p_to ), $row->p2p_to );
			echo '<br>';
		}

		die;
	}
}

add_action( 'plugins_loaded', array( 'P2P_Debug', 'init' ), 11 );

