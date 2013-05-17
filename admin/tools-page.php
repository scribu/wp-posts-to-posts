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

		update_option( 'p2p_storage', P2P_Storage::$version );
	}

	function form_handler() {
		if ( empty( $_POST['p2p_convert'] ) )
			return false;

		check_admin_referer( $this->nonce );

		global $wpdb;

		$old_p2p_type = $_POST['old_p2p_type'];
		$new_p2p_type = $_POST['new_p2p_type'];

		if ( !p2p_type( $new_p2p_type ) ) {
			$this->admin_msg( sprintf( __( '<em>%s</em> is not a registered connection type.', P2P_TEXTDOMAIN ), esc_html( $new_p2p_type ) ) );
			return;
		}

		$count = $wpdb->update( $wpdb->p2p,
			array( 'p2p_type' => $new_p2p_type ),
			array( 'p2p_type' => $old_p2p_type )
		);

		$this->admin_msg( sprintf( __( 'Converted %1$s connections from <em>%2$s</em> to <em>%3$s</em>.', P2P_TEXTDOMAIN ),
			number_format_i18n( $count ),
			esc_html( $old_p2p_type ),
			esc_html( $new_p2p_type )
		) );
	}

	function page_head() {
		wp_enqueue_style( 'p2p-tools', plugins_url( 'tools.css', __FILE__ ), array(), P2P_PLUGIN_VERSION );
	}

	function page_content() {
		$data = array(
			'columns' => array(
				__( 'Name', P2P_TEXTDOMAIN ),
				__( 'Information', P2P_TEXTDOMAIN ),
				__( 'Connections', P2P_TEXTDOMAIN ),
			)
		);

		$connection_counts = $this->get_connection_counts();

		if ( empty( $connection_counts ) ) {
			$data['has-rows'] = false;
			$data['no-rows'] = __( 'No connection types registered.', P2P_TEXTDOMAIN );
			$data['no-rows2'] = sprintf(
				__( 'To register a connection type, see <a href="%s">the wiki</a>.', P2P_TEXTDOMAIN ),
				'https://github.com/scribu/wp-posts-to-posts/wiki/'
			);
		} else {
			$data['has-rows'] = array(true);

			foreach ( $connection_counts as $p2p_type => $count ) {
				$row = array(
					'p2p_type' => $p2p_type,
					'count' => number_format_i18n( $count )
				);

				$ctype = p2p_type( $p2p_type );

				if ( $ctype ) {
					$row['desc'] = $ctype->get_desc();
				} else {
					$row['desc'] = __( 'Convert to registered connection type:', P2P_TEXTDOMAIN ) . scbForms::form_wrap( $this->get_dropdown( $p2p_type ), $this->nonce );
					$row['class'] = 'error';
				}

				$data['rows'][] = $row;
			}
		}

		echo P2P_Mustache::render( 'connection-types', $data );
	}

	private function get_connection_counts() {
		global $wpdb;

		$counts = $wpdb->get_results( "
			SELECT p2p_type, COUNT(*) as count
			FROM $wpdb->p2p
			GROUP BY p2p_type
		" );

		$counts = scb_list_fold( $counts, 'p2p_type', 'count' );

		foreach ( P2P_Connection_Type_Factory::get_all_instances() as $p2p_type => $ctype ) {
			if ( !isset( $counts[ $p2p_type ] ) )
				$counts[ $p2p_type ] = 0;
		}

		ksort( $counts );

		return $counts;
	}

	private function get_dropdown( $p2p_type ) {
		$data = array(
			'old_p2p_type' => $p2p_type,
			'options' => array_keys( P2P_Connection_Type_Factory::get_all_instances() ),
			'button_text' => __( 'Go', P2P_TEXTDOMAIN )
		);

		return P2P_Mustache::render( 'connection-types-form', $data );
	}
}

