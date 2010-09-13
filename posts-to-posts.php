<?php
/*
Plugin Name: Posts 2 Posts
Version: 0.4-alpha6
Plugin Author: scribu
Description: Create connections between posts of different types
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
	require dirname( __FILE__ ) . '/storage.php';
	require dirname( __FILE__ ) . '/api.php';
	require dirname( __FILE__ ) . '/ui/ui.php';
	require dirname( __FILE__ ) . '/ui/boxes.php';

	P2P_Connections::init( __FILE__ );
	P2P_Query::init();
	P2P_Connection_Types::init();
	P2P_Box_Multiple::init();
}
scb_init( '_p2p_init' );

