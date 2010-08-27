<?php

class P2P_Storage {
	const TAX = 'p2p';

	function init() {
		add_action( 'init', array( __CLASS__, 'setup' ) );
		add_action( 'delete_post', array( __CLASS__, 'delete_post' ) );

		add_action( 'admin_notices', array( __CLASS__, 'migrate' ) );
		add_action( 'admin_notices', array( __CLASS__, 'uninstall' ) );
	}

	function migrate() {
		if ( !isset( $_GET['migrate_p2p'] ) || !current_user_can( 'administrator' ) )
			return;

		global $wpdb;

		$rows = $wpdb->get_results( "
			SELECT post_id as post_a, meta_value as post_b
			FROM $wpdb->postmeta
			WHERE meta_key = '_p2p'
		" );

		$grouped = array();
		foreach ( $rows as $row )
			$grouped[ $row->post_a ][] = $row->post_b;

		foreach ( $grouped as $post_a => $post_b )
			p2p_connect( $post_a, $post_b );

		$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key = '_p2p'" );

		printf( "<div class='updated'><p>Migrated %s connections.</p></div>", count( $rows ) );
	}

	function uninstall() {
		if ( !isset( $_GET['delete_p2p'] ) || !current_user_can( 'administrator' ) )
			return;

		$terms = get_terms( P2P_Storage::TAX, array( 'fields' => 'ids' ) );

		foreach ( $terms as $term_id )
			wp_delete_term( $term_id, P2P_Storage::TAX );

		echo "<div class='updated'><p>Posts 2 Posts data deleted.</p></div>";
	}

	function setup() {
		register_taxonomy( self::TAX, 'post', array( 'public' => false ) );
	}

	function delete_post( $post_id ) {
		wp_delete_term( self::convert( 'term', $post_id ), self::TAX );
	}

	function connect( $post_a, $post_b ) {
		if ( empty( $post_a ) )
			return;

		$terms = self::convert( 'term', $post_b );

		if ( empty( $terms ) )
			return;

		wp_set_object_terms( $post_a, $terms, self::TAX, true );
	}

	function disconnect( $post_a, $post_b ) {
		if ( empty( $post_a ) )
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
		if ( 'from' == $direction ) {
			$terms = wp_get_object_terms( $post_id, self::TAX, array( 'fields' => 'names' ) );
			return self::convert( 'post', $terms );
		} else {
			$term = get_term_by( 'slug', reset( self::convert( 'term', $post_id ) ), self::TAX );
			if ( !$term )
				return array();

			return get_objects_in_term( $term->term_id, self::TAX );
		}
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

P2P_Storage::init();
