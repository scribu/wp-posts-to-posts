<?php

class P2P_Unit_Tests extends WP_UnitTestCase {

	var $plugin_slug = 'p2p/posts-to-posts';

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

	private function generate_post( $type ) {
		$posts = $this->generate_posts( $type, 1 );
		return $posts[0];
	}

	function setUp() {
		parent::setUp();

		P2P_Storage::install();

		foreach ( array( 'actor', 'movie', 'studio' ) as $ptype )
			register_post_type( $ptype );

		p2p_register_connection_type( array(
			'id' => 'normal',
			'from' => 'actor',
			'to' => 'movie'
		) );
	}

	function test_storage() {
		$post_id = $this->generate_post( 'actor' );
		$actor_id = $this->generate_post( 'movie' );

		p2p_type( 'normal' )->connect( $post_id, $actor_id );
		p2p_type( 'normal' )->connect( $actor_id, $post_id, array( 'foo' => 'bar' ) );

		wp_delete_post( $post_id, true );

		$this->assertEmpty( p2p_get_connections( 'normal', array( 'from' => $post_id, 'fields' => 'p2p_id' ) ) );
		$this->assertEmpty( p2p_get_connections( 'normal', array( 'to' => $post_id, 'fields' => 'p2p_id' ) ) );
	}

	function test_p2p_types() {
		// make sure a unique id is generated when none is given
		$ctype = @p2p_register_connection_type( 'studio', 'movie' );
		$this->assertTrue( strlen( $ctype->name ) > 0 );
	}

	function test_direction() {
		$normal = p2p_type( 'normal' );

		$this->assertEquals( 'from', $normal->find_direction( 'actor' )->get_direction() );
		$this->assertEquals( 'to', $normal->find_direction( 'movie' )->get_direction() );
		$this->assertFalse( $normal->find_direction( 'post' ) );

		// 'from' array
		$ctype = @p2p_register_connection_type( array( 'actor', 'movie' ), 'studio' );
		$this->assertInstanceOf( 'P2P_Connection_Type', $ctype );

		$this->assertEquals( 'from', $ctype->find_direction( 'actor' )->get_direction() );
		$this->assertEquals( 'from', $ctype->find_direction( 'movie' )->get_direction() );
		$this->assertEquals( 'to', $ctype->find_direction( 'studio' )->get_direction() );

		$this->assertFalse( $ctype->find_direction( 'post' ) );

		// 'to' array
		$ctype = @p2p_register_connection_type( 'actor', array( 'movie', 'studio' ) );
		$this->assertInstanceOf( 'P2P_Connection_Type', $ctype );

		$this->assertEquals( 'from', $ctype->find_direction( 'actor' )->get_direction() );
		$this->assertEquals( 'to', $ctype->find_direction( 'movie' )->get_direction() );
		$this->assertEquals( 'to', $ctype->find_direction( 'studio' )->get_direction() );

		$this->assertFalse( $ctype->find_direction( 'post' ) );

		// indeterminate
		$indeterminate = @p2p_register_connection_type( 'actor', 'actor' );
		$this->assertInstanceOf( 'P2P_Connection_Type', $indeterminate );

		$this->assertFalse( $indeterminate->find_direction( 'post' ) );
		$this->assertEquals( 'from', $indeterminate->find_direction( 'actor' )->get_direction() );

		// reciprocal
		$reciprocal = @p2p_register_connection_type( array(
			'from' => 'movie',
			'to' => 'movie',
			'reciprocal' => true
		) );
		$this->assertInstanceOf( 'P2P_Connection_Type', $reciprocal );

		$this->assertEquals( 'any', $reciprocal->find_direction( 'movie' )->get_direction() );
	}

	function test_ctype_api() {
		$actor_id = $this->generate_post( 'actor' );
		$movie_id = $this->generate_post( 'movie' );

		$ctype = p2p_type( 'normal' );

		// create connection
		$p2p_id_1 = $ctype->connect( $actor_id, $movie_id );
		$this->assertTrue( (int) $p2p_id_1 > 0 );

		// 'prevent_duplicates'
		$p2p_id_2 = $ctype->connect( $actor_id, $movie_id );
		$this->assertFalse( $p2p_id_2 );

		// get connected
		$this->assertEquals( array( $movie_id ), $ctype->get_connected( $actor_id, array( 'fields' => 'ids' ) )->posts );
		$this->assertEquals( array( $actor_id ), $ctype->get_connected( $movie_id, array( 'fields' => 'ids' ) )->posts );

		// delete connection
		$ctype->disconnect( $actor_id, $movie_id );
		$this->assertFalse( $ctype->get_p2p_id( $actor_id, $movie_id ) );
	}

	function test_extra_qv() {
		$ctype = @p2p_register_connection_type( array(
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
		$ctype = p2p_type( 'normal' );

		$actor_ids = $this->generate_posts( 'actor', 3 );
		$movie_id = $this->generate_post( 'movie' );

		$p2p_id_0 = $ctype->connect( $actor_ids[0], $movie_id );
		$p2p_id_1 = $ctype->connect( $actor_ids[1], $movie_id );

		$query = new WP_Query( array(
			'post_type' => 'actor',
			'post__in' => $actor_ids,
			'order' => 'ASC'
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

	function test_posts_to_users() {
		$ctype = p2p_register_connection_type( array(
			'from' => 'post',
			'to_object' => 'user',
		) );

		$post_id = $this->generate_post( 'post' );
		$user_id = 1;

		$ctype->connect( $post_id, $user_id );

		$connected = get_users( array(
			'connected_type' => $ctype->name,
			'connected_items' => $post_id
		) );

		$this->assertEquals( array( $user_id ), wp_list_pluck( $connected, 'ID' ) );
	}
}

