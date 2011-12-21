<?php
/*
Plugin Name: Posts 2 Posts
Description: Create many-to-many relationships between all types of posts.
Version: 1.1.3
Author: scribu
Author URI: http://scribu.net/
Plugin URI: http://scribu.net/wordpress/posts-to-posts
Text Domain: posts-to-posts
Domain Path: /lang


Copyright (C) 2010-2011 Cristi Burcă (mail@scribu.net)

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

define( 'P2P_PLUGIN_VERSION', '1.1.3' );

define( 'P2P_TEXTDOMAIN', 'posts-to-posts' );

require dirname( __FILE__ ) . '/scb/load.php';

function _p2p_init() {
	$base = dirname( __FILE__ );

	load_plugin_textdomain( P2P_TEXTDOMAIN, '', basename( $base ) . '/lang' );

	_p2p_load_files( "$base/core", array(
		'storage', 'query', 'query-post', 'query-user', 'url-query',
		'util', 'side', 'type-factory', 'type', 'directed-type',
		'api', 'widget'
	) );

	P2P_Widget::init();

	if ( is_admin() ) {
		_p2p_load_files( "$base/admin", array(
			'utils',
			'box-factory', 'box', 'fields',
			'column-factory', 'column'
		) );
	}

	register_uninstall_hook( __FILE__, array( 'P2P_Storage', 'uninstall' ) );
}
scb_init( '_p2p_init' );


function _p2p_load_files( $dir, $files ) {
	foreach ( $files as $file )
		require_once "$dir/$file.php";
}

