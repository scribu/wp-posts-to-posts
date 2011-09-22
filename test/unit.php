<?php

#define( 'WP_ADMIN', true );

class P2P_Unit_Tests extends WP_UnitTestCase {

	var $plugin_slug = 'p2p/posts-to-posts';

	function test_basic_api() {
		register_post_type('actor');
		register_post_type('movie');

		global $wpdb;

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

		p2p_connect( array_slice( $actor_ids, 0, 5 ), array_slice( $movie_ids, 0, 3 ) );
		p2p_connect( $movie_ids[0], $actor_ids[10] );

		$result = array_values( p2p_get_connected( $actor_ids[0], 'from' ) );
		sort( $result );
		$expected = array_slice( $movie_ids, 0, 3 );
		sort($expected);
		$this->assertEquals( $expected, $result );

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
}

