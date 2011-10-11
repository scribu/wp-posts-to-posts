<?php

define( 'WP_ADMIN', true );

class P2P_Unit_Tests extends WP_UnitTestCase {

	var $plugin_slug = 'p2p/posts-to-posts';

	protected $actor_ids = array();
	protected $movie_ids = array();

	function setUp() {
		parent::setUp();

		foreach ( array( 'actor', 'movie', 'studio' ) as $ptype )
			register_post_type( $ptype );

		for ( $i=0; $i<20; $i++ ) {
			$this->actor_ids[] = wp_insert_post(array(
				'post_type' => 'actor',
				'post_title' => "Actor $i",
				'post_status' => 'publish'
			));

			$this->movie_ids[] = wp_insert_post(array(
				'post_type' => 'movie',
				'post_title' => "Movie $i",
				'post_status' => 'publish'
			));
		}
	}

	function test_basic_api() {
		global $wpdb;

		$actor_ids = $this->actor_ids;
		$movie_ids = $this->movie_ids;

		$expected = array_slice( $movie_ids, 0, 3 );
		sort($expected);

		p2p_connect( array_slice( $actor_ids, 0, 5 ), $expected );
		p2p_connect( $movie_ids[0], $actor_ids[10] );

		$result = array_values( p2p_get_connected( $actor_ids[0], 'from' ) );
		sort( $result );

		$this->assertEquals( $result, $expected );

		$result = array_values( p2p_get_connected( $movie_ids[0], 'any' ) );
		sort( $result );
		$expected = array( $actor_ids[0], $actor_ids[10] );
		sort( $expected );
		$this->assertEquals( $expected, $result );

		$this->assertTrue( p2p_is_connected( $actor_ids[0], $movie_ids[0] ) );
		$this->assertFalse( p2p_is_connected( $actor_ids[0], $movie_ids[10] ) );

		// 'actor' => 'actor'
		$posts = get_posts( array(
			'connected' => $actor_ids[0],
			'post_type' => 'actor',
			'post_status' => 'any',
			'suppress_filters' => false,
			'fields' => 'ids',
		) );
		$this->assertEmpty( $posts );

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

		$r = scb_list_fold( $posts, 'p2p_id', 'ID' );

		$this->assertEquals( array_intersect_assoc( $r, $raw ), $r );
	}

	function test_connection_types() {
		$normal = p2p_register_connection_type( 'actor', 'movie' );

		$this->assertInstanceOf( 'P2P_Connection_Type', $normal );

		$this->assertEquals( 'from', $normal->get_direction( 'actor' ) );
		$this->assertEquals( 'to', $normal->get_direction( 'movie' ) );
		$this->assertFalse( $normal->get_direction( 'post' ) );

		// 'from' array
		$ctype = p2p_register_connection_type( array( 'actor', 'movie' ), 'studio' );
		$this->assertInstanceOf( 'P2P_Connection_Type', $ctype );

		$this->assertEquals( 'from', $ctype->get_direction( 'actor' ) );
		$this->assertEquals( 'from', $ctype->get_direction( 'movie' ) );
		$this->assertEquals( 'to', $ctype->get_direction( 'studio' ) );

		$this->assertFalse( $ctype->get_direction( 'post' ) );

		// 'to' array
		$ctype = p2p_register_connection_type( 'actor', array( 'movie', 'studio' ) );
		$this->assertInstanceOf( 'P2P_Connection_Type', $ctype );

		$this->assertEquals( 'from', $ctype->get_direction( 'actor' ) );
		$this->assertEquals( 'to', $ctype->get_direction( 'movie' ) );
		$this->assertEquals( 'to', $ctype->get_direction( 'studio' ) );

		$this->assertFalse( $ctype->get_direction( 'post' ) );
	}

	function test_reflexive_connections() {
		$reflexive = p2p_register_connection_type( 'actor', 'actor' );

		$this->assertInstanceOf( 'P2P_Connection_Type', $reflexive );

		$this->assertEquals( 'any', $reflexive->get_direction( 'actor' ) );
		$this->assertFalse( $reflexive->get_direction( 'post' ) );
	}

	function test_ctype_api() {
		$actor_id = $this->actor_ids[0];
		$movie_id = $this->movie_ids[0];

		$ctype = @p2p_register_connection_type( 'actor', 'movie' );

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
}

