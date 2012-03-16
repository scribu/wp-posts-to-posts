<?php

class P2P_Tools_Page extends scbAdminPage {

	function setup() {
		$this->args = array(
			'page_title' => __( 'Posts 2 Posts', P2P_TEXTDOMAIN ),
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
				admin_url( 'tools.php?page=posts-2-posts&p2p-upgrade=1' )
			) );
		} else {
			update_option( 'p2p_storage', P2P_Storage::$version );
		}
	}

	function page_content() {

	}
}

new P2P_Tools_Page( false );

