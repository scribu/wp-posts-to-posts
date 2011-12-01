<?php

class P2P_Tools extends scbAdminPage {

	function setup() {
		$this->args = array(
			'page_title' => __( 'P2P Tools', P2P_TEXTDOMAIN ),
			'parent' => 'tools.php',
			'page_slug' => 'p2p-tools'
		);
	}

	function page_head() {
		if ( isset( $_GET['upgrade'] ) )
			$this->migrate();
	}

	function page_content() {
	}

	private function migrate() {
		global $wpdb;

		$n = 0;

		foreach ( P2P_Connection_Type_Factory::get_all_instances() as $p2p_type => $ctype ) {
			if ( ! $ctype instanceof P2P_Connection_Type )
				continue;

			$args = $ctype->set_direction( 'any' )->get_connected_args( array(
				'connected_items' => 'any',
				'cache_results' => false,
				'post_status' => 'any',
				'nopaging' => true
			) );
			unset( $args['p2p_type'] );

			foreach ( get_posts( $args ) as $post ) {
				// some connections might be ambiguous, spanning multiple connection types; first one wins
				if ( $post->p2p_type )
					continue;

				$n += $wpdb->update( $wpdb->p2p, compact( 'p2p_type' ), array( 'p2p_id' => $post->p2p_id ) );
			}
		}

		$subquery = "SELECT ID FROM $wpdb->posts";

		$wpdb->query( "DELETE FROM $wpdb->p2p WHERE p2p_from NOT IN ($subquery) OR p2p_to NOT IN ($subquery)" );

		update_option( 'p2p_storage', P2P_Storage::$version );

		$this->admin_msg( sprintf( __( 'Upgraded %d connections.', P2P_TEXTDOMAIN ), $n ) );
	}
}

