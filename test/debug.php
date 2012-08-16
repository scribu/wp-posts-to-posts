<?php

class P2P_Debug {

	function init() {
		self::posts_to_attachments();
		self::posts_to_users();
		self::contacts_and_tickets();
		self::actors_and_movies();

#		self::reset_upgrade();

		/* add_action('admin_notices', array(__CLASS__, 'setup_example')); */
		/* add_action('admin_notices', array(new P2P_Debug, 'playground')); */
	}

	function playground() {
		p2p_register_connection_type( array(
			'name' => 'actor_to_movie',
			'from' => 'actor',
			'to' => 'movie',
			'sortable' => true,
		) );

		$ctype = p2p_type( 'actor_to_movie' );

		$actor_ids = self::generate_posts( 'actor', 3 );
		list( $movie_id ) = self::generate_posts( 'movie', 1 );

		$p2p_id_0 = $ctype->connect( $actor_ids[0], $movie_id );
		$p2p_id_1 = $ctype->connect( $actor_ids[1], $movie_id );

		$query = new WP_Query( array(
			'post_type' => 'any',
			'post__in' => array( $actor_ids[0], $actor_ids[1], $movie_id ),
			'orderby' => 'ID',
			'order' => 'ASC'
		) );

		$ctype->each_connected( $query );

		$this->assertEquals( $p2p_id_0, $query->posts[0]->connected[0]->p2p_id );
		$this->assertEquals( $p2p_id_1, $query->posts[1]->connected[0]->p2p_id );
		$this->assertEquals( $p2p_id_0, $query->posts[2]->connected[0]->p2p_id );
		$this->assertEquals( $p2p_id_1, $query->posts[2]->connected[1]->p2p_id );
	}

	function assertEquals( $a, $b ) {
		if ( $a != $b ) {
			trigger_error( "$a != $b", E_USER_WARNING );
		}
	}

	private function generate_posts( $type, $count = 20 ) {
		global $wpdb;

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'publish'" );

		$ids = array();

		for ( $i = $total; $i < $total + $count; $i++ ) {
			$ids[] = wp_insert_post(array(
				'post_type' => $type,
				'post_title' => "Post $i",
				'post_status' => 'publish'
			));
		}

		return $ids;
	}

	function posts_to_attachments() {
		p2p_register_connection_type( array(
			'name' => 'posts_to_attachments',
			'from' => 'post',
			'to' => 'attachment'
		) );
	}

	function reset_upgrade() {
		global $wpdb;

		$wpdb->query( "UPDATE $wpdb->p2p SET p2p_type = ''" );

		update_option( 'p2p_storage', 3 );
	}

	function posts_to_users() {
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
			'sortable' => true,
		) );
	}

	function contacts_and_tickets() {
		register_post_type( 'ticket', array(
			'label' => 'Tickets',
			'public' => true,
			'supports' => array( 'title' )
		) );

		register_post_type( 'contact', array(
			'label' => 'Contacts',
			'public' => true,
			'supports' => array( 'title' )
		) );

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

	function actors_and_movies() {
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
			'can_create_post' => false,
			'to_labels' => array(
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
			'from_labels' => array(
				'create' => __( 'CONNECT YO' )
			),
			'admin_column' => 'any'
		) );
	}

	function setup_example() {
		$ctype = p2p_type( 'actor_movie' );

		$data = array(
			'Nicholas Cage' => array( 'Lord Of War', 'Adaptation' ),
			'Jude Law' => array( 'Sherlock Holmes' ),
			'Brad Pitt' => array( '7 Years In Tibet', 'Fight Club' ),
			'Natalie Portman' => array( 'Black Swan', 'Thor' ),
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

add_action( 'p2p_init', array( 'P2P_Debug', 'init' ), 11 );

