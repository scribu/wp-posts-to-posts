<?php

// Automated testing suite for the Posts 2 Posts plugin

class P2P_Test {

	function init() {
		if ( !function_exists('p2p_register_connection_type') )
			return;

		add_action('init', array(__CLASS__, '_init'));
#		add_action('admin_init', array(__CLASS__, 'setup'));
		add_action('load-index.php', array(__CLASS__, 'test'));
#		add_action('load-index.php', array(__CLASS__, 'debug'));
	}

	function _init() {
		register_post_type('actor', array(
			'public' => true,
			'labels' => array(
				'name' => 'Actors',
				'singular_name' => 'Actor',
				'search_items' => 'Search Actors',
				'not_found' => 'No actors found.'
			),
			'has_archive' => 'actors'
		));
		register_post_type('movie', array(
			'public' => true,
			'labels' => array(
				'name' => 'Movies',
				'singular_name' => 'Movie',
				'search_items' => 'Search movies',
				'not_found' => 'No movies found.',
			)
		) );

		p2p_register_connection_type( array(
			'from' => 'actor', 
			'to' => 'actor', 
			'reciprocal' => true,
			'title' => array( 'from' => 'Doubles', 'to' => 'Main Actor' )
		) );

		p2p_register_connection_type( array(
			'from' => 'actor', 
			'to' => 'movie', 
			'reciprocal' => true,
			'fields' => array(
				'role' => 'Role',
				'role_type' => 'Role Type'
			),
			'prevent_duplicates' => false,
			'title' => array( 'from' => 'Played In', 'to' => 'Cast' ),
		) );

		p2p_register_connection_type('actor', 'post');
	}

	function setup() {
		global $wpdb;

		$wpdb->query("DELETE FROM $wpdb->posts WHERE post_type IN ('actor', 'movie')");

		$movie_ids = $actor_ids = array();

		for ( $i=0; $i<20; $i++ ) {
			$actor_ids[] = wp_insert_post(array(
				'post_type' => 'actor',
				'post_title' => "Actor $i",
				'post_status' => 'publish'
			));

			$movie_ids[] = wp_insert_post(array(
				'post_type' => 'movie',
				'post_title' => "Movie $i",
				'post_status' => 'publish'
			));
		}
	}

	function test() {
		global $wpdb;

		$wpdb->query("TRUNCATE $wpdb->p2p");
		$wpdb->query("TRUNCATE $wpdb->p2pmeta");

		assert_options(ASSERT_ACTIVE, 1);
		assert_options(ASSERT_WARNING, 0);
		assert_options(ASSERT_QUIET_EVAL, 1);

		$failed = false;

		assert_options(ASSERT_CALLBACK, function ($file, $line, $code) use ( &$failed ) {
			$failed = true;
		
			echo "<hr>Assertion Failed (line $line):<br />
				<code>$code</code><br /><hr />";

			add_action('admin_notices', array(__CLASS__, 'debug'));
		});

		$actor_ids = get_posts( array(
			'fields' => 'ids',
			'post_type' => 'actor',
			'post_status' => 'any',
			'orderby' => 'post_title',
			'order' => 'asc',
			'nopaging' => true
		) );

		$movie_ids = get_posts( array(
			'fields' => 'ids',
			'post_type' => 'movie',
			'post_status' => 'any',
			'orderby' => 'post_title',
			'order' => 'asc',
			'nopaging' => true
		) );

		// basic API correctness
		p2p_connect( array_slice( $actor_ids, 0, 5 ), array_slice( $movie_ids, 0, 3 ) );
		p2p_connect( $movie_ids[0], $actor_ids[10] );

		$result = array_values( p2p_get_connected( $actor_ids[0], 'from' ) );
		sort( $result );
		$expected = array_slice( $movie_ids, 0, 3 );
		sort($expected);
		assert( '$expected == $result' );

		$result = array_values( p2p_get_connected( $movie_ids[0], 'any' ) );
		sort( $result );
		$expected = array( $actor_ids[0], $actor_ids[10] );
		sort( $expected );
		assert( '$expected == $result' );

		assert( 'true == p2p_is_connected( $actor_ids[0], $movie_ids[0] )' );
		assert( 'false == p2p_is_connected( $actor_ids[0], $movie_ids[10] )' );

		// 'actor' => 'actor'
		$posts = get_posts( array(
			'connected' => $actor_ids[0],
			'post_type' => 'actor',
			'post_status' => 'any',
			'suppress_filters' => false,
			'fields' => 'ids',
		) );
		assert( 'array() == $posts' );

		// compare p2p_get_connected() to 'connected' => 123
		p2p_connect( $actor_ids[2], $actor_ids[10] );
		p2p_connect( $actor_ids[10], $actor_ids[2] );

		$raw = p2p_get_connected($actor_ids[2], 'any');

		$posts = get_posts( array(
			'connected' => $actor_ids[2],
			'post_type' => 'actor',
			'post_status' => 'any',
			'suppress_filters' => false,
			'cache_results' => false,
		) );

		$r = array();
		foreach ( $posts as $post ) {
			$r[ $post->p2p_id ] = $post->ID;
		}

		assert( 'array_intersect_assoc($r, $raw) == $r' );

		// test 'each_*' query vars
		$posts = get_posts( array(
			'post_type' => 'actor',
			'post_status' => 'any',
			'nopaging' => true,
			'each_connected' => array(
				'post_type' => 'movie',
				'post_status' => 'any',
				'nopaging' => true,
			),
			'suppress_filters' => false
		) );

#		self::walk( $posts );

#		// test 'each_*' query vars
#		$posts = get_posts( array(
#			'post_type' => 'actor',
#			'post_status' => 'any',
#			'nopaging' => true,
#			'each_connected' => array(
#				'post_type' => 'actor',
#				'post_status' => 'any',
#				'nopaging' => true,
#				'each_connected' => array(
#					'post_type' => 'actor',
#					'post_status' => 'any',
#					'nopaging' => true,
#				),
#			),
#			'suppress_filters' => false
#		) );

#		self::walk( $posts );

#		// test p2p_each_connected()
#		$query = new WP_Query( array(
#			'post_type' => 'actor',
#			'post_status' => 'any',
#			'nopaging' => true,
#		) );

#		p2p_each_connected( 'any', 'movies', array( 'post_type' => 'movie' ), $query );

#		self::walk( $query->posts, 'movies' );

		if ( $failed )
			self::debug();
	}

	private function walk( $posts, $key = '', $level = 0 ) {
		if ( 0 == $level )
			echo '<pre>';

		foreach ( $posts as $post ) {
			echo str_repeat( "\t", $level ) . "$post->ID: $post->post_title\n";
			self::walk( (array) @$post->{"connected_$key"}, $key, $level+1 );
		}

		if ( 0 == $level )
			echo '</pre>';
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

add_action( 'plugins_loaded', array('P2P_Test', 'init'), 11 );

