<?php
/*
Plugin Name: Posts 2 Posts
Description: Create many-to-many relationships between all types of posts.
Version: 0.9
Author: scribu
Author URI: http://scribu.net/
Plugin URI: http://scribu.net/wordpress/posts-to-posts
Text Domain: posts-to-posts
Domain Path: /lang


Copyright (C) 2010-2011 Cristi Burcă (scribu@gmail.com)

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

define( 'P2P_PLUGIN_VERSION', '0.9' );

define( 'P2P_TEXTDOMAIN', 'posts-to-posts' );

require dirname( __FILE__ ) . '/scb/load.php';

function _p2p_init() {
	$base = dirname( __FILE__ );

	load_plugin_textdomain( P2P_TEXTDOMAIN, '', basename( $base ) . '/lang' );

	foreach ( array( 'storage', 'query', 'type', 'api' ) as $file )
		require_once "$base/core/$file.php";

	if ( is_admin() ) {
		foreach ( array( 'base', 'box', 'fields' ) as $file )
			require_once "$base/admin/$file.php";
	}
}
scb_init( '_p2p_init' );

function _p2p_append( &$arr, $values ) {
	$arr = array_merge( $arr, $values );
}

