<?php

// Abstraction layer for connection storage

class Posts2Posts {
	const TAX = 'p2p';

	function init() {
		add_action( 'init', array( __CLASS__, 'setup' ) );
		add_action( 'delete_post', array( __CLASS__, 'delete_post' ) );
	}

	function setup() {
		register_taxonomy( self::TAX, 'post', array( 'public' => false ) );
	}

	function delete_post( $post_id ) {
		wp_delete_term( self::convert( 'term', $post_id ), self::TAX );
	}

	function connect( $post_a, $post_b ) {
		if ( empty($post_a) )
			return;

		$terms = self::convert( 'term', $post_b );

		if ( empty( $terms ) )
			return;

		wp_set_object_terms( $post_a, $terms, self::TAX, true );
	}

	function disconnect( $post_a, $post_b ) {
		if ( empty($post_a) )
			return;

		$terms = self::convert( 'term', $post_b );

		if ( empty( $terms ) )
			return;

		$list = wp_get_object_terms( $post_a, self::TAX, 'fields=names' );

		wp_set_object_terms( $post_a, array_diff( $list, $terms ), self::TAX );
	}

	function is_connected( $post_a, $post_b ) {
		$terms = self::convert( 'term', $post_b );

		return is_object_in_term( $post_a, $terms, self::TAX );
	}

	function get_connected( $post_id, $direction ) {
		if ( 'from' == $direction )
			return self::convert( 'post', wp_get_object_terms( $post_id, self::TAX, array( 'fields' => 'names' ) ) );
		else
			return get_objects_in_term( self::convert( 'term', $post_id ), self::TAX );
	}

	// Add a 'p' to avoid confusion with term ids
	private function convert( $to, $ids ) {
		$ids = array_filter( (array) $ids );

		if ( 'term' == $to )
			foreach ( $ids as &$id )
				$id = 'p' . $id;
		else
			foreach ( $ids as &$id )
				$id = substr( $id, 1 );

		return $ids;
	}
}

