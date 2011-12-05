<?php

class P2P_Debug {

	function init() {
		if ( !function_exists('p2p_register_connection_type') )
			return;

		add_action('init', array(__CLASS__, '_init'));
		//add_action('admin_notices', array(__CLASS__, 'setup_example'));
		add_action('admin_notices', array(__CLASS__, 'test_chain'));
	}

	function _init() {
		self::posts_to_users();
		self::contacts_and_tickets();
		self::actors_and_movies();
	}

	function posts_to_users() {
		p2p_register_connection_type( array(
			'name' => 'posts_to_users',
			'to_object' => 'user',
			'title' => array( 'from' => 'Posts 2 Users' )
		) );

		p2p_register_connection_type( array(
			'name' => 'users_to_posts',
			'from_object' => 'user',
			'title' => array( 'to' => 'Users 2 Posts' ),
			'sortable' => true
		) );
	}

	function contacts_and_tickets() {
		register_post_type( 'contact', array(
			'label' => 'Contacts',
			'public' => true,
			'supports' => array( 'title' )
		) );

		register_post_type( 'ticket', array(
			'label' => 'Tickets',
			'public' => true,
			'supports' => array( 'title' )
		) );

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
				'show_ui' => 'from'
			) );
		}

		p2p_register_connection_type(array(
			'name' => 'posts_to_contact',
			'from' => 'contact',
			'to' => 'contact',
			'title' => 'Registry',
			'show_ui' => 'any'
		));

		p2p_register_connection_type(array(
			'name' => 'ticket_to_contact',
			'from' => 'ticket',
			'to' => 'contact',
			'reciprocal' => true
		));

		p2p_register_connection_type(array(
			'name' => 'ticket_to_post',
			'from' => 'ticket',
			'to' => 'post',
			'reciprocal' => true
		));
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
			'supports' => array( 'title' )
		));

		register_post_type('movie', array(
			'public' => true,
			'labels' => array(
				'name' => 'Movies',
				'singular_name' => 'Movie',
				'search_items' => 'Search movies',
				'new_item' => 'New Movie',
				'not_found' => 'No movies found.',
			),
			'supports' => array( 'title' )
		) );


		p2p_register_connection_type( array(
			'name' => 'actor_movie',
			'from' => 'actor',
			'to' => 'movie',
			'fields' => array(
				'role' => 'Role',
				'role_type' => array(
					'title' => 'Role Type',
					'values' => array( 'lead', 'secondary', 'episodic' )
				),
				'single_checkbox' => array(
					'title' => 'Ya?',
					'type' => 'checkbox',
				),
				'color' => array(
					'title' => 'Color',
					'type' => 'checkbox',
					'values' => array(
						'white', 'red', 'green', 'blue'
					)
				),
			),
			'sortable' => 'any',
			'prevent_duplicates' => false,
			'context' => 'advanced',
			'admin_box' => 'any',
			'admin_column' => 'any',
			'title' => array(
				'from' => 'Played In',
				'to' => 'Cast'
			)
		) );

		p2p_register_connection_type( array(
			'from' => 'actor',
			'to' => 'actor',
			'cardinality' => 'one-to-many',
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
	}

	function setup_example() {
		$ctype = p2p_type( 'actor_movie' );

		$data = array(
			'Nicholas Cage' => array( 'Lord Of War', 'Adaptation' ),
			'Jude Law' => array( 'Sherlock Holmes' ),
			'Brad Pitt' => array( '7 Years In Tibet' ),
			'Natalie Portam' => array( 'Black Swan' ),
			'Charlize Theron' => array()
		);

		foreach ( $data as $actor_name => $movies ) {
			$actor_id = self::make_post( 'actor',  $actor_name );

			foreach ( $movies as $movie_title ) {
				$movie_id = self::make_post( 'movie', $movie_title );

				$ctype->connect( $actor_id, $movie_id );
			}
		}
	}

	function test_chain() {
		//$q = new WP_Query( array(
			//'connected_items' => 1139,
			//'connected_chain' => array( 'posts_to_users', 'posts_to_users' )
		//) );

		$related = wp_list_pluck( p2p_type( 'actor_movie' )->get_related( 623 )->posts, 'ID' );

		debug( $related );

		$q = new WP_Query( array(
			'fields' => 'ids',
			'connected_items' => 623,
			'connected_chain' => array( 'actor_movie', 'actor_movie' )
		) );

		debug($q->request);
		debug($q->posts);
	}

	private function make_post( $type, $title ) {
		return wp_insert_post( array(
			'post_type' => $type,
			'post_title' => $title,
			'post_status' => 'publish'
		) );
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

