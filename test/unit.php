<?php

define( 'WP_ADMIN', true );

class P2P_Unit_Tests extends WP_UnitTestCase {

	var $plugin_slug = 'p2p/posts-to-posts';

	private function generate_posts( $type, $count = 20 ) {
		$ids = array();

		for ( $i=0; $i < $count; $i++ ) {
			$ids[] = wp_insert_post(array(
				'post_type' => $type,
				'post_title' => "Post $i",
				'post_status' => 'publish'
			));
		}

		return $ids;
	}

	private function generate_post( $type ) {
		$posts = $this->generate_posts( $type, 1 );
		return $posts[0];
	}

	function setUp() {
		parent::setUp();

		foreach ( array( 'actor', 'movie', 'studio' ) as $ptype )
			register_post_type( $ptype );

		@p2p_register_connection_type( array(
			'id' => 'normal',
			'from' => 'actor',
			'to' => 'movie'
		) );
	}

	function test_ids() {
		$this->assertInstanceOf( 'P2P_Connection_Type', p2p_type( 'normal' ) );

		// make sure a unique id is generated when none is given
		$ctype = p2p_register_connection_type( 'studio', 'movie' );
		$this->assertTrue( strlen( $ctype->id ) > 0 );
	}

	function test_direction() {
		$normal = p2p_type( 'normal' );

		$this->assertEquals( 'from', $normal->find_direction( 'actor' )->direction );
		$this->assertEquals( 'to', $normal->find_direction( 'movie' )->direction );
		$this->assertFalse( $normal->find_direction( 'post' ) );

		// 'from' array
		$ctype = p2p_register_connection_type( array( 'actor', 'movie' ), 'studio' );
		$this->assertInstanceOf( 'P2P_Connection_Type', $ctype );

		$this->assertEquals( 'from', $ctype->find_direction( 'actor' )->direction );
		$this->assertEquals( 'from', $ctype->find_direction( 'movie' )->direction );
		$this->assertEquals( 'to', $ctype->find_direction( 'studio' )->direction );

		$this->assertFalse( $ctype->find_direction( 'post' ) );

		// 'to' array
		$ctype = p2p_register_connection_type( 'actor', array( 'movie', 'studio' ) );
		$this->assertInstanceOf( 'P2P_Connection_Type', $ctype );

		$this->assertEquals( 'from', $ctype->find_direction( 'actor' )->direction );
		$this->assertEquals( 'to', $ctype->find_direction( 'movie' )->direction );
		$this->assertEquals( 'to', $ctype->find_direction( 'studio' )->direction );

		$this->assertFalse( $ctype->find_direction( 'post' ) );

		// reflexive
		$reflexive = p2p_register_connection_type( 'actor', 'actor' );
		$this->assertInstanceOf( 'P2P_Connection_Type', $reflexive );

		$this->assertEquals( 'any', $reflexive->find_direction( 'actor' )->direction );

		$this->assertFalse( $reflexive->find_direction( 'post' ) );

		// reflexive, but not reciprocal
		$reflexive = p2p_register_connection_type( array( 'from' => 'movie', 'to' => 'movie', 'reciprocal' => false ) );
		$this->assertInstanceOf( 'P2P_Connection_Type', $reflexive );

		$this->assertEquals( 'from', $reflexive->find_direction( 'movie' )->direction );
	}

	function test_each_connected() {
		$ctype = p2p_type( 'normal' );

		$actor_ids = $this->generate_posts( 'actor', 3 );
		$movie_id = $this->generate_post( 'movie' );

		$p2p_id_0 = $ctype->connect( $actor_ids[0], $movie_id );
		$p2p_id_1 = $ctype->connect( $actor_ids[1], $movie_id );

		$query = new WP_Query( array(
			'post_type' => 'actor',
			'post__in' => $actor_ids
		) );

		$ctype->each_connected( $query );

		$this->assertEquals( $query->posts[0]->connected[0]->ID, $movie_id );
		$this->assertEquals( $query->posts[1]->connected[0]->p2p_id, $p2p_id_1 );
		$this->assertEmpty( $query->posts[2]->connected );
	}

	function test_each_connected_post() {
		$ctype = @p2p_register_connection_type( 'post', 'actor' );

		$post_id = $this->generate_post( 'post' );
		$actor_id = $this->generate_post( 'actor' );

		$ctype->connect( $post_id, $actor_id );

		// Test if each_connected() works correctly when 'post_type' is not explicitly set
		$query = new WP_Query( array(
			'post__in' => array( $post_id )
		) );

		$ctype->each_connected( $query );

		$this->assertEquals( $query->posts[0]->connected[0]->ID, $actor_id );
	}

	function test_ctype_api() {
		$actor_id = $this->generate_post( 'actor' );
		$movie_id = $this->generate_post( 'movie' );

		$ctype = p2p_type( 'normal' );

		// create connection
		$p2p_id_1 = $ctype->connect( $actor_id, $movie_id );
		$this->assertInternalType( 'int', $p2p_id_1 );

		// 'prevent_duplicates'
		$p2p_id_2 = $ctype->connect( $actor_id, $movie_id );
		$this->assertEquals( $p2p_id_1, $p2p_id_2 );

		// delete connection
		$ctype->disconnect( $actor_id, $movie_id );
		$this->assertFalse( $ctype->get_p2p_id( $actor_id, $movie_id ) );
	}

	function test_buckets() {
		$ctype = p2p_type( 'normal' );

		$actor_id = $this->generate_post( 'actor' );
		$movie_id = $this->generate_post( 'movie' );

		$green_p2pid = P2P_Storage::connect( $actor_id, $movie_id, array( 'color' => 'green' ) );
		$orange_p2pid = P2P_Storage::connect( $actor_id, $movie_id, array( 'color' => 'orange' ) );

		$buckets = p2p_split_posts( p2p_type( 'normal' )->get_connected( $actor_id ), 'color' );

		$this->assertEquals( $green_p2pid, $buckets['green'][0]->p2p_id );
		$this->assertEquals( $orange_p2pid, $buckets['orange'][0]->p2p_id );
	}
}

