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
		if ( isset( $_GET['migrate'] ) )
			$this->migrate();
	}

	function page_content() {
	}

	private function migrate() {
		global $wpdb;

		$n = 0;

		foreach ( P2P_Connection_Type::get_all_instances() as $p2p_type => $ctype ) {
			// TODO: this will only work until p2p_type is queried for
			$connections = $ctype->set_direction( 'any' )->get_connected( 'any', array( 'fields' => 'p2p_id' ) );

			foreach ( $connections as $p2p_id ) {
				$n += $wpdb->update( $wpdb->p2p, compact( 'p2p_type' ), compact( 'p2p_id' ) );
			}
		}

		update_option( 'p2p_storage', P2P_Storage::$version );

		$this->admin_msg( sprintf( __( 'Migrated %d connections.', P2P_TEXTDOMAIN ), $n ) );
	}
}

