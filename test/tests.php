<?php

class P2P_Unit_Tests extends WP_UnitTestCase {

	function setUp() {
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

	function test_storage_post() {
		$actor = $this->generate_post( 'actor' );
		$movie = $this->generate_post( 'movie' );

		p2p_type( 'actor_to_movie' )->connect( $actor, $movie );

		wp_delete_post( $actor->ID, true );

		$this->assertFalse( p2p_connection_exists( 'actor_to_movie', array( 'from' => $actor->ID ) ) );
	}

	function test_storage_user() {
		$post_id = $this->generate_post( 'post' )->ID;
		$user_id = $this->generate_user()->ID;

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

	function test_direction_types_user() {
		$ctypes = p2p_type( 'posts_to_users' );

		$this->assertEquals( 'from', $ctypes->direction_from_types( 'post', 'post' ) );
		$this->assertEquals( 'to', $ctypes->direction_from_types( 'user' ) );

		$this->assertFalse( $ctypes->direction_from_types( 'post', 'page' ) );
	}

	function test_direction_normal() {
		$normal = p2p_type( 'actor_to_movie' );

		$this->assertEquals( $normal, $normal->set_direction( 'to' )->set_direction( 'from' )->lose_direction() );

		$this->assertEquals( 'from', $normal->find_direction( $this->generate_post( 'actor' ), false ) );
		$this->assertEquals( 'to', $normal->find_direction( $this->generate_post( 'movie' ), false ) );
		$this->assertFalse( $normal->find_direction( $this->generate_post( 'post' ) ) );
	}

	function test_direction_types_normal() {
		$normal = p2p_type( 'actor_to_movie' );

		$this->assertEquals( 'from', $normal->direction_from_types( 'post', 'actor' ) );
		$this->assertEquals( 'to', $normal->direction_from_types( 'post', 'movie' ) );

		$this->assertFalse( $normal->direction_from_types( 'post', 'page' ) );
	}

	function test_direction_array_from() {
		$ctype = @p2p_register_connection_type( array( 'actor', 'movie' ), 'studio' );
		$this->assertFalse( $ctype->indeterminate );

		$this->assertEquals( 'from', $ctype->find_direction( $this->generate_post( 'actor' ), false ) );
		$this->assertEquals( 'from', $ctype->find_direction( $this->generate_post( 'movie' ), false ) );
		$this->assertEquals( 'to', $ctype->find_direction( $this->generate_post( 'studio' ), false ) );

		$this->assertFalse( $ctype->find_direction( $this->generate_post( 'post' )  ) );
	}

	function test_direction_array_to() {
		$ctype = @p2p_register_connection_type( 'actor', array( 'movie', 'studio' ) );
		$this->assertFalse( $ctype->indeterminate );

		$this->assertEquals( 'from', $ctype->find_direction( $this->generate_post( 'actor' ), false ) );
		$this->assertEquals( 'to', $ctype->find_direction( $this->generate_post( 'movie' ), false ) );
		$this->assertEquals( 'to', $ctype->find_direction( $this->generate_post( 'studio' ), false ) );

		$this->assertFalse( $ctype->find_direction( $this->generate_post( 'post' ) ) );
	}

	function test_direction_indeterminate() {
		$indeterminate = p2p_type( 'movies_to_movies' );

		$this->assertTrue( $indeterminate->indeterminate );

		$this->assertFalse( $indeterminate->find_direction( $this->generate_post( 'post' ) ) );
		$this->assertEquals( 'from', $indeterminate->find_direction( $this->generate_post( 'movie' ), false ) );
	}

	function test_direction_types_indeterminate() {
		$indeterminate = p2p_type( 'movies_to_movies' );

		$this->assertFalse( $indeterminate->direction_from_types( 'post' ) );
		$this->assertEquals( 'from', $indeterminate->direction_from_types( 'post', 'movie' ) );
	}

	function test_direction_reciprocal() {
		$reciprocal = @p2p_register_connection_type( array(
			'from' => 'movie',
			'to' => 'movie',
			'reciprocal' => true
		) );

		$this->assertEquals( 'any', $reciprocal->find_direction( $this->generate_post( 'movie' ), false ) );
		$this->assertEquals( 'any', $reciprocal->direction_from_types( 'post', 'movie' ) );
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

		$actor_id = $this->generate_post( 'actor' )->ID;
		$movie_id = $this->generate_post( 'movie' )->ID;

		// create connection
		$this->assertFalse( is_wp_error( $ctype->connect( $actor_id, $movie_id ) ) );

		// 'self_connections'
		$r = p2p_type( 'movies_to_movies' )->connect( $movie_id, $movie_id );
		$this->assertEquals( 'self_connection', $r->get_error_code() );

		// 'duplicate_connections'
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

		$r = $ctype->connect( $actor_ids[1], $movie_ids[0] );

		$this->assertTrue( is_wp_error( $r ) );
		$this->assertEquals( 'cardinality_current', $r->get_error_code() );

		$r = $ctype->connect( $movie_ids[0], $actor_ids[1] );

		$this->assertTrue( is_wp_error( $r ) );
		$this->assertEquals( 'cardinality_opposite', $r->get_error_code() );
	}

	function test_query_direction() {
		$qv = array(
			'connected_type' => 'actor_to_movie',
			'connected_items' => $this->generate_post( 'post' )
		);

		$p2p_q = P2P_Query::create_from_qv( $qv, 'post' );

		$this->assertTrue( is_wp_error( $p2p_q ) );
	}

	function test_extra_qv() {
		$ctype = p2p_register_connection_type( array(
			'name' => 'posts_to_pages',
			'from' => 'post',
			'to' => 'page',
			'data' => array(
				'type' => 'strong'
			),
			'sortable' => true,
			'from_query_vars' => array(
				'post_status' => 'publish',
				'connected_order' => 'asc',
			)
		) );

		$qv = array(
			'connected_type' => $ctype->name,
			'connected_direction' => 'to',
			'connected_meta' => array( 'foo' => 'bar' ),
			'connected_orderby' => 'foo',
			'connected_order' => 'desc',
		);

		$p2p_query = P2P_Query::create_from_qv( $qv, 'post' );

		// 'to_query_vars' should automatically be added
		$this->assertEquals( 'publish', $qv['post_status'] );

		// users should be able to filter connections via additional connected meta
		$this->assertEquals( $p2p_query->meta, array_merge( $ctype->data, array( 'foo' => 'bar' ) ) );

		// users should be able to specify a different order
		$this->assertEquals( 'foo', $p2p_query->orderby );
		$this->assertEquals( 'desc', $p2p_query->order );
	}

	function test_not_each_connected() {
		$actor_ids = $this->generate_posts( 'actor', 3 );

		$query = new WP_Query( array(
			'post_type' => 'actor',
		) );

		p2p_type( 'movies_to_movies' )->each_connected( $query );

		$this->assertFalse( isset( $query->posts[0]->connected ) );
	}

	function test_each_connected() {
		$ctype = p2p_type( 'actor_to_movie' );

		$actor_ids = $this->generate_posts( 'actor', 3 );
		$movie = $this->generate_post( 'movie' );

		$p2p_id_0 = $ctype->connect( $actor_ids[0], $movie );
		$p2p_id_1 = $ctype->connect( $actor_ids[1], $movie );

		$query = new WP_Query( array(
			'post_type' => 'actor',
			'post__in' => $actor_ids,
			'orderby' => 'ID',
			'order' => 'ASC'
		) );

		$ctype->each_connected( $query );

		$this->assertEquals( $query->posts[0]->connected[0]->ID, $movie->ID );
		$this->assertEquals( $query->posts[1]->connected[0]->p2p_id, $p2p_id_1 );
		$this->assertEmpty( $query->posts[2]->connected );
	}

	function test_each_connected_users() {
		$ctype = p2p_type( 'posts_to_users' );

		$post_ids = $this->generate_posts( 'post', 3 );
		$user = $this->generate_user();

		$p2p_id_0 = $ctype->connect( $post_ids[0], $user );
		$p2p_id_1 = $ctype->connect( $post_ids[1], $user );

		$query = new WP_Query( array(
			'post_type' => 'post',
			'post__in' => $post_ids,
			'orderby' => 'ID',
			'order' => 'ASC'
		) );

		$ctype->each_connected( $query );

		$this->assertEquals( $query->posts[0]->connected[0]->ID, $user->ID );
		$this->assertEquals( $query->posts[1]->connected[0]->p2p_id, $p2p_id_1 );
		$this->assertEmpty( $query->posts[2]->connected );
	}

	// Test if each_connected() works correctly with mixed post types
	function test_each_connected_any() {
		$ctype = p2p_type( 'actor_to_movie' );

		$actor_ids = $this->generate_posts( 'actor', 3 );
		$movie_id = $this->generate_post( 'movie' )->ID;

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

		$actor = $this->generate_post( 'actor' );
		$movie_ids = $this->generate_posts( 'movie', 3 );

		$key = $ctype->set_direction( 'from' )->get_orderby_key();

		foreach ( $movie_ids as $i => $movie_id ) {
			$ctype->connect( $actor, $movie_id, array( $key => $i ) );
		}

		$this->assertEquals( $ctype->get_prev( $movie_ids[1], $actor )->ID, $movie_ids[0] );
		$this->assertEquals( $ctype->get_next( $movie_ids[1], $actor )->ID, $movie_ids[2] );
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
		$user_id = $this->generate_user()->ID;

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

	function test_connected_query() {
		$user = $this->generate_user();
		$post = $this->generate_post();

		update_post_meta( $post->ID, 'foo', 'bar' );

		$ctype = p2p_type( 'posts_to_users' );

		$p2p_id = $ctype->connect( $user, $post );

		// another connection that shouldn't show up
		$ctype->connect( $this->generate_user(), $this->generate_post() );

		$connected = get_users( array(
			'connected_type' => $ctype->name,
			'connected_query' => array(
				'meta_key' => 'foo',
				'meta_value' => 'bar'
			)
		) );

		$this->assertEquals( 1, count( $connected ) );

		$this->assertEquals( $p2p_id, $connected[0]->p2p_id );
	}

	function test_get_connectable() {
		$post = $this->generate_posts( 'post', 2 );
		$page = $this->generate_posts( 'page', 2 );

		$ctype = p2p_register_connection_type( array(
			'name' => __FUNCTION__,
			'from' => 'post',
			'to' => 'page',
		) );

		$compare_ids = function( $id_list, $collection ) {
			$resulting_ids = wp_list_pluck( $collection->items, 'ID' );

			sort( $id_list );
			sort( $resulting_ids );

			return $id_list == $resulting_ids;
		};

		$ctype->connect( $post[0], $page[0] );

		$candidate = $ctype->get_connectable( $post[0] );
		$this->assertTrue( $compare_ids( array( $page[1] ), $candidate ) );

		$candidate = $ctype->get_connectable( $post[1] );
		$this->assertTrue( $compare_ids( $page, $candidate ) );
	}

	function test_p2p_list_posts() {
		$list = array_map( 'get_post', $this->generate_posts( 'post', 2 ) );

		$GLOBALS['post'] = $list[1];

		p2p_list_posts( $list, array( 'echo' => false ) );

		$this->assertEquals( $GLOBALS['post'], $list[1] );
	}

	private function generate_posts( $type, $count ) {
		return $this->factory->post->create_many( $count, array(
			'post_type' => $type
		) );
	}

	private function generate_post( $type = 'post' ) {
		return $this->factory->post->create_and_get( array(
			'post_type' => $type
		) );
	}

	private function generate_user() {
		return $this->factory->user->create_and_get();
	}
}

