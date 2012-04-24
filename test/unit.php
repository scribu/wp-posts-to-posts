<?php

class P2P_Unit_Tests extends WP_UnitTestCase {

	function setUp() {
		$this->plugin_slug = basename( dirname( dirname( __FILE__ ) ) ) . '/posts-to-posts';

		parent::setUp();

		P2P_Storage::install();

		foreach ( array( 'actor', 'movie', 'studio' ) as $ptype )
			register_post_type( $ptype, array( 'public' => true ) );

		if ( !p2p_type( 'actor_to_movie' ) ) {
			p2p_register_connection_type( array(
				'name' => 'actor_to_movie',
				'from' => 'actor',
				'to' => 'movie',
				'sortable' => true
			) );
		}

		if ( !p2p_type( 'movies_to_movies' ) ) {
			p2p_register_connection_type( array(
				'name' => 'movies_to_movies',
				'from' => 'movie',
				'to' => 'movie',
			) );
		}

		if ( !p2p_type( 'posts_to_users' ) ) {
			p2p_register_connection_type( array(
				'name' => 'posts_to_users',
				'from' => 'post',
				'to' => 'user',
			) );
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

	private function generate_post( $type = 'post' ) {
		$posts = $this->generate_posts( $type, 1 );
		return $posts[0];
	}

	private function generate_user() {
		global $wpdb;

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->users" );

		return wp_insert_user( array(
			'user_login' => 'user_' . $total,
			'user_pass' => ''
		) );
	}

	function test_storage_post() {
		$actor_id = $this->generate_post( 'actor' );
		$movie_id = $this->generate_post( 'movie' );

		p2p_type( 'actor_to_movie' )->connect( $actor_id, $movie_id );

		wp_delete_post( $actor_id, true );

		$this->assertFalse( p2p_connection_exists( 'actor_to_movie', array( 'from' => $actor_id ) ) );
	}

	function test_storage_user() {
		$post_id = $this->generate_post( 'post' );
		$user_id = $this->generate_user();

		p2p_create_connection( 'posts_to_users', array(
			'from' => $post_id,
			'to' => $user_id
		) );

		wp_delete_user( $user_id, true );

		$this->assertFalse( p2p_connection_exists( 'posts_to_users', array( 'from' => $post_id ) ) );
	}

	function test_annonymous_ctypes() {
		$ctype = @p2p_register_connection_type( 'studio', 'movie' );
		$this->assertTrue( strlen( $ctype->name ) > 0 );
	}

	function test_direction() {
		$normal = p2p_type( 'actor_to_movie' );

		$this->assertEquals( $normal, $normal->set_direction( 'to' )->set_direction( 'from' )->lose_direction() );

		$this->assertEquals( 'from', $normal->find_direction( 'actor', false ) );
		$this->assertEquals( 'to', $normal->find_direction( 'movie', false ) );
		$this->assertFalse( $normal->find_direction( 'post' ) );

		// 'from' array
		$ctype = @p2p_register_connection_type( array( 'actor', 'movie' ), 'studio' );
		$this->assertFalse( $ctype->indeterminate );

		$this->assertEquals( 'from', $ctype->find_direction( 'actor', false ) );
		$this->assertEquals( 'from', $ctype->find_direction( 'movie', false ) );
		$this->assertEquals( 'to', $ctype->find_direction( 'studio', false ) );

		$this->assertFalse( $ctype->find_direction( 'post' ) );

		// 'to' array
		$ctype = @p2p_register_connection_type( 'actor', array( 'movie', 'studio' ) );
		$this->assertFalse( $ctype->indeterminate );

		$this->assertEquals( 'from', $ctype->find_direction( 'actor', false ) );
		$this->assertEquals( 'to', $ctype->find_direction( 'movie', false ) );
		$this->assertEquals( 'to', $ctype->find_direction( 'studio', false ) );

		$this->assertFalse( $ctype->find_direction( 'post' ) );

		// indeterminate
		$indeterminate = p2p_type( 'movies_to_movies' );
		$this->assertTrue( $indeterminate->indeterminate );

		$this->assertFalse( $indeterminate->find_direction( 'post' ) );
		$this->assertEquals( 'from', $indeterminate->find_direction( 'movie', false ) );

		// reciprocal
		$reciprocal = @p2p_register_connection_type( array(
			'from' => 'movie',
			'to' => 'movie',
			'reciprocal' => true
		) );

		$this->assertEquals( 'any', $reciprocal->find_direction( 'movie', false ) );
	}

	function test_restrict_post_type() {
		$ctype = p2p_register_connection_type( array(
			'name' => 'movie_bizz',
			'from' => 'user',
			'to' => array( 'movie', 'studio' )
		) );

		$q = new WP_Query( array(
			'post_type' => 'post',
			'connected_type' => 'movie_bizz',
			'connected_items' => $this->generate_user()
		) );

		$this->assertEquals( array( 'movie', 'studio' ), $q->query_vars['post_type'] );

		$q = new WP_Query( array(
			'post_type' => 'studio',
			'connected_type' => 'movie_bizz',
			'connected_items' => $this->generate_user()
		) );

		$this->assertEquals( 'studio', $q->query_vars['post_type'] );
	}

	function test_connection_create() {
		$ctype = p2p_type( 'actor_to_movie' );

		$actor_id = $this->generate_post( 'actor' );
		$movie_id = $this->generate_post( 'movie' );

		// create connection
		$this->assertFalse( is_wp_error( $ctype->connect( $actor_id, $movie_id ) ) );

		// 'self_connections'
		$r = p2p_type( 'movies_to_movies' )->connect( $movie_id, $movie_id );
		$this->assertEquals( 'self_connection', $r->get_error_code() );

		// 'prevent_duplicates'
		$this->assertTrue( is_wp_error( $ctype->connect( $actor_id, $movie_id ) ) );

		// get connected
		$this->assertEquals( array( $movie_id ), $ctype->get_connected( $actor_id, array( 'fields' => 'ids' ) )->posts );
		$this->assertEquals( array( $actor_id ), $ctype->get_connected( $movie_id, array( 'fields' => 'ids' ) )->posts );

		// delete connection
		$ctype->disconnect( $actor_id, $movie_id );
		$this->assertFalse( $ctype->get_p2p_id( $actor_id, $movie_id ) );
	}

	function test_cardinality() {
		$ctype = p2p_register_connection_type( array(
			'name' => 'actor_to_movies',
			'from' => 'actor',
			'to' => 'movie',
			'cardinality' => 'one-to-many'
		) );

		$actor_ids = $this->generate_posts( 'actor', 2 );
		$movie_ids = $this->generate_posts( 'movie', 2 );

		$this->assertFalse( is_wp_error( $ctype->connect( $actor_ids[0], $movie_ids[0] ) ) );
		$this->assertFalse( is_wp_error( $ctype->connect( $actor_ids[0], $movie_ids[1] ) ) );

		$this->assertTrue( is_wp_error( $ctype->connect( $actor_ids[1], $movie_ids[0] ) ) );
		$this->assertTrue( is_wp_error( $ctype->connect( $movie_ids[0], $actor_ids[1] ) ) );
	}

	function test_wp_query() {
		$q = new WP_Query( array(
			'connected_type' => 'actor_to_movie',
			'connected_items' => $this->generate_post( 'post' )
		) );

		$this->assertEmpty( $q->posts );
	}

	function test_extra_qv() {
		$ctype = p2p_register_connection_type( array(
			'name' => 'posts_to_pages',
			'from' => 'post',
			'to' => 'page',
			'data' => array(
				'type' => 'strong'
			),
			'sortable',
		) );

		$directed = $ctype->set_direction( 'to' );

		// users should be able to filter connections via additional connected meta
		$query = $directed->get_connected( 1, array(
			'connected_meta' => array( 'foo' => 'bar' )
		) );

		$this->assertEquals( $query->get( 'connected_meta' ), array_merge( $ctype->data, array( 'foo' => 'bar' ) ) );

		// users should be able to specify a different order
		$extra_qv = array(
			'connected_orderby' => 'foo',
			'connected_order' => 'desc',
		);

		$query = $directed->get_connected( 1, $extra_qv );

		foreach ( $extra_qv as $key => $value ) {
			$this->assertEquals( $value, $query->get( $key ) );
		}
	}

	function test_each_connected() {
		$ctype = p2p_type( 'actor_to_movie' );

		$actor_ids = $this->generate_posts( 'actor', 3 );
		$movie_id = $this->generate_post( 'movie' );

		$p2p_id_0 = $ctype->connect( $actor_ids[0], $movie_id );
		$p2p_id_1 = $ctype->connect( $actor_ids[1], $movie_id );

		$query = new WP_Query( array(
			'post_type' => 'actor',
			'post__in' => $actor_ids,
			'orderby' => 'ID',
			'order' => 'ASC'
		) );

		$ctype->each_connected( $query );

		$this->assertEquals( $query->posts[0]->connected[0]->ID, $movie_id );
		$this->assertEquals( $query->posts[1]->connected[0]->p2p_id, $p2p_id_1 );
		$this->assertEmpty( $query->posts[2]->connected );
	}

	// Test if each_connected() works correctly with mixed post types
	function test_each_connected_any() {
		$ctype = p2p_type( 'actor_to_movie' );

		$actor_ids = $this->generate_posts( 'actor', 3 );
		$movie_id = $this->generate_post( 'movie' );

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

	function test_adjacent() {
		$ctype = p2p_type( 'actor_to_movie' );

		$actor_id = $this->generate_post( 'actor' );
		$movie_ids = $this->generate_posts( 'movie', 3 );

		$key = $ctype->set_direction( 'from' )->get_orderby_key();

		foreach ( $movie_ids as $i => $movie_id ) {
			$ctype->connect( $actor_id, $movie_id, array( $key => $i ) );
		}

		$this->assertEquals( $ctype->get_prev( $movie_ids[1], $actor_id )->ID, $movie_ids[0] );
		$this->assertEquals( $ctype->get_next( $movie_ids[1], $actor_id )->ID, $movie_ids[2] );
	}

	function test_related() {
		$ctype = p2p_type( 'actor_to_movie' );

		$actor_ids = $this->generate_posts( 'actor', 2 );
		$movie_ids = $this->generate_posts( 'movie', 2 );

		foreach ( $movie_ids as $i => $movie_id ) {
			$ctype->connect( $actor_ids[0], $movie_id );
		}

		$related = $ctype->get_related( get_post( $movie_ids[0] ) )->posts;
		$this->assertEquals( array( $movie_ids[1] ), wp_list_pluck( $related, 'ID' ) );
	}

	function test_posts_to_users() {
		$post_ids = $this->generate_posts( 'post', 2 );
		$user_id = $this->generate_user();

		$ctype = p2p_type( 'posts_to_users' );

		$ctype->connect( $post_ids[0], $user_id );
		$ctype->connect( $post_ids[1], $user_id );

		$connected = get_users( array(
			'fields' => 'ids',
			'connected_type' => 'posts_to_users',
			'connected_items' => $post_ids[0]
		) );

		$this->assertEquals( array( $user_id ), $connected );

		$related = $ctype->get_related( $post_ids[0] )->posts;
		$this->assertEquals( array( $post_ids[1] ), wp_list_pluck( $related, 'ID' ) );
	}

	function test_object_passing() {
		$post = get_post( $this->generate_post() );
		$user = new WP_User( $this->generate_user() );

		$ctype = p2p_type( 'posts_to_users' );

		$this->assertEquals( 'from', $ctype->find_direction( $post, false ) );
		$this->assertEquals( 'to', $ctype->find_direction( $user, false ) );

		$this->assertTrue( !is_wp_error( $ctype->connect( $user, $post ) ) );
		$this->assertEquals( 1, $ctype->disconnect( $post, $user ) );
	}

	function test_p2p_list_posts() {
		$list = array_map( 'get_post', $this->generate_posts( 'post', 2 ) );

		$GLOBALS['post'] = $list[1];

		p2p_list_posts( $list, array( 'echo' => false ) );

		$this->assertEquals( $GLOBALS['post'], $list[1] );
	}
}

