<?php

require_once __DIR__ . '/debug-utils.php';

add_action( 'p2p_init', array( 'P2P_Debug', 'init' ), 11 );

class P2P_Debug {

	static function init() {
		if ( defined( 'WP_CLI' ) )
			require_once __DIR__ . '/command.php';

		self::posts_to_attachments();
		self::posts_to_users();
		self::contacts_and_tickets();
		self::actors_and_movies();
	}

	static function posts_to_attachments() {
		p2p_register_connection_type( array(
			'name' => 'posts_to_attachments',
			'from' => 'post',
			'to' => 'attachment'
		) );
	}

	static function posts_to_users() {
		p2p_register_connection_type( array(
			'name' => 'pages_to_users',
			'from' => 'page',
			'to' => 'user',
			'to_query_vars' => array(
				'role' => 'editor'
			),
			'title' => array( 'from' => 'Pages 2 Editors' ),
			'admin_column' => 'any'
		) );

		p2p_register_connection_type( array(
			'name' => 'users_to_posts',
			'from' => 'user',
			'cardinality' => 'one-to-one',
			'title' => array( 'to' => 'Users 2 Posts' ),
			'fields' => array(
				'text' => array(
					'title' => 'Text',
					'default' => 'foobar'
				),
				'select' => array(
					'type' => 'select',
					'title' => 'Select',
					'values' => range(1, 10),
					'default' => 5
				),
				'single_checkbox' => array(
					'title' => 'Ya?',
					'type' => 'checkbox',
					'default' => true
				),
				'color' => array(
					'title' => 'Color',
					'type' => 'checkbox',
					'values' => array( 'white', 'red', 'green', 'blue' ),
					'default' => 'green'
				),
			),
			'admin_box' => array(
				'context' => 'advanced'
			),
			'admin_dropdown' => 'any',
			'sortable' => true,
		) );
	}

	static function contacts_and_tickets() {
		$types = array(
			'bug' => 'Bug',
			'feature' => 'Feature'
		);

		foreach ( $types as $type => $title ) {
			p2p_register_connection_type( array(
				'name' => "contact_to_$type",
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
			'name' => 'ticket_to_contact',
			'from' => 'ticket',
			'to' => 'contact',
			'cardinality' => 'one-to-many'
		));

		p2p_register_connection_type(array(
			'name' => 'posts_to_contact',
			'from' => 'contact',
			'to' => 'contact',
			'title' => 'Registry',
		));

		p2p_register_connection_type(array(
			'name' => 'ticket_to_stuff',
			'from' => 'ticket',
			'to' => array( 'post', 'page' )
		));
	}

	static function actors_and_movies() {
		register_post_type('actor', array(
			'public' => true,
			'labels' => array(
				'name' => 'Actors',
				'singular_name' => 'Actor',
				'search_items' => 'Search Actors',
				'new_item' => 'New Actor',
				'not_found' => 'No actors found.',
				'add_new_item' => 'Add new &agrave;ctor'
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
					'values' => array( 'lead', 'secondary', '\\episodic' )
				),
			),
			'sortable' => 'any',
			'prevent_duplicates' => false,
			'admin_box' => array(
				'show' => 'any',
				'context' => 'advanced',
				'priority' => 'high'
			),
			'admin_column' => 'any',
			'admin_dropdown' => 'any',
			'title' => array(
				'from' => 'Played In',
				'to' => 'Cast'
			)
		) );

		p2p_register_connection_type( array(
			'name' => 'actor_doubles',
			'from' => 'actor',
			'to' => 'actor',
			'cardinality' => 'one-to-many',
			'title' => array( 'from' => 'Doubles', 'to' => 'Main Actor' ),
			'data' => array( 'type' => 'doubles' ),
			'sortable' => 'order',
			'admin_column' => 'any',
			'admin_dropdown' => 'any',
			'can_create_post' => false,
			'from_labels' => array(
				'help' => 'The main actor this actor was doubling for.',
			),
			'to_labels' => array(
				'help' => 'Other actors that have played as doubles for this actor.',
				'column_title' => 'XXX Doubles',
				'singular_name' => 'Double',
				'search_items' => 'Search doubles',
				'not_found' => 'No doubles found'
			),
		) );

		p2p_register_connection_type( array(
			'name' => 'actor_friends',
			'from' => array( 'actor' ),
			'to' => 'actor',
			'reciprocal' => true,
			'title' => 'Friends with',
			'data' => array( 'type' => 'friends' ),
			'to_labels' => array(
				'create' => 'Add friends'
			),
			'admin_column' => 'any'
		) );
	}
}

