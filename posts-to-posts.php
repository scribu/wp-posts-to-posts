<?php
/*
Plugin Name: Posts 2 Posts
Version: 0.7
Plugin Author: scribu
Description: Create many-to-many relationships between all types of posts
Author URI: http://scribu.net/
Plugin URI: http://scribu.net/wordpress/posts-to-posts
Text Domain: posts-to-posts
Domain Path: /lang


Copyright (C) 2010 Cristi Burcă (scribu@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

require dirname( __FILE__ ) . '/scb/load.php';

function _p2p_init() {
	load_plugin_textdomain( 'posts-to-posts', '', dirname( plugin_basename( __FILE__ ) ) . '/lang' );

	require_once dirname( __FILE__ ) . '/storage.php';
	require_once dirname( __FILE__ ) . '/api.php';
	require_once dirname( __FILE__ ) . '/ui/ui.php';
	require_once dirname( __FILE__ ) . '/ui/boxes.php';

	P2P_Connections::init( __FILE__ );
	P2P_Query::init();
	P2P_Connection_Types::init();

	P2P_Migrate::init();
}
scb_init( '_p2p_init' );


class P2P_Migrate {

	function init() {
		add_action( 'admin_notices', array( __CLASS__, 'migrate' ) );
	}

	function migrate() {
		if ( !isset( $_GET['migrate_p2p'] ) || !current_user_can( 'administrator' ) )
			return;

		$tax = 'p2p';

		register_taxonomy( $tax, 'post', array( 'public' => false ) );

		$count = 0;
		foreach ( get_terms( $tax ) as $term ) {
			$post_b = (int) substr( $term->slug, 1 );
			$post_a = get_objects_in_term( $term->term_id, $tax );

			p2p_connect( $post_a, $post_b );

			wp_delete_term( $term->term_id, $tax );

			$count += count( $post_a );
		}

		printf( "<div class='updated'><p>Migrated %d connections.</p></div>", $count );
	}
}

