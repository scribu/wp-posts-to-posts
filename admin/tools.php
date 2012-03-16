<?php

class P2P_Tools_Page extends scbAdminPage {

	function setup() {
		$this->args = array(
			'page_title' => __( 'Connection Types', P2P_TEXTDOMAIN ),
			'page_slug' => 'connection-types',
			'parent' => 'tools.php'
		);

		add_action( 'admin_notices', array( $this, 'maybe_install' ) );
	}

	function maybe_install() {
		if ( !current_user_can( 'manage_options' ) )
			return;

		$current_ver = get_option( 'p2p_storage' );

		if ( $current_ver == P2P_Storage::$version )
			return;

		P2P_Storage::install();

		if ( isset( $_GET['p2p-upgrade'] ) ) {
			$n = P2P_Storage::upgrade();

			update_option( 'p2p_storage', P2P_Storage::$version );

			echo scb_admin_notice( sprintf( __( 'Upgraded %d connections.', P2P_TEXTDOMAIN ), $n ) );
		} elseif ( $current_ver ) {
			echo scb_admin_notice( sprintf(
				__( 'The Posts 2 Posts connections need to be upgraded. <a href="%s">Proceed.</a>', P2P_TEXTDOMAIN ),
				admin_url( 'tools.php?page=connection-types&p2p-upgrade=1' )
			) );
		} else {
			update_option( 'p2p_storage', P2P_Storage::$version );
		}
	}

	function page_head() {
		wp_enqueue_style( 'p2p-tools', plugins_url( 'tools.css', __FILE__ ), array(), P2P_PLUGIN_VERSION );
	}

	function page_content() {
		global $wpdb;

		$stats = $wpdb->get_results( "
			SELECT p2p_type, COUNT(*) as count
			FROM $wpdb->p2p
			GROUP BY p2p_type
		" );

		$data = array(
			'columns' => array(
				__( 'Name', P2P_TEXTDOMAIN ),
				__( 'Description', P2P_TEXTDOMAIN ),
				__( 'Connections', P2P_TEXTDOMAIN ),
			)
		);

		foreach ( scb_list_fold( $stats, 'p2p_type', 'count' ) as $p2p_type => $count ) {
			$row = array(
				'p2p_type' => $p2p_type,
				'count' => number_format_i18n( $count )
			);

			$ctype = p2p_type( $p2p_type );

			if ( $ctype ) {
				$row['desc'] = $ctype->get_desc();
			} else {
				$row['class'] = 'error';
			}

			$data['rows'][] = $row;
		}

		echo P2P_Mustache::render( 'connection-types', $data );
	}
}

new P2P_Tools_Page( false );

