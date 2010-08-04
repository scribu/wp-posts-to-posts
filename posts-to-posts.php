<?php
/*
Plugin Name: Posts 2 Posts
Version: 0.3-alpha2
Plugin Author: scribu
Description: Create connections between posts of different types
Author URI: http://scribu.net/
Plugin URI: http://scribu.net/wordpress/posts-to-posts
Text Domain: posts-to-posts
Domain Path: /lang


Copyright ( C ) 2010 scribu.net ( scribu AT gmail DOT com )

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
( at your option ) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

require dirname( __FILE__ ) . '/scb/load.php';

function _p2p_init() {
	require dirname( __FILE__ ) . '/core.php';
	require dirname( __FILE__ ) . '/api.php';

	Posts2Posts::init();

	if ( is_admin() ) {
		require dirname( __FILE__ ) . '/admin/admin.php';
		P2P_Admin::init( __FILE__ );
	}
}
scb_init( '_p2p_init' );

