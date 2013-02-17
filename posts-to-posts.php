<?php
/*
Plugin Name: Posts 2 Posts
Description: Create many-to-many relationships between all types of posts.
Version: 1.5.1
Author: scribu
Author URI: http://scribu.net/
Plugin URI: http://scribu.net/wordpress/posts-to-posts
Text Domain: posts-to-posts
Domain Path: /lang
*/

define( 'P2P_PLUGIN_VERSION', '1.5' );

define( 'P2P_TEXTDOMAIN', 'posts-to-posts' );

require_once dirname( __FILE__ ) . '/scb/load.php';

function _p2p_load() {
	$base = dirname( __FILE__ );

	load_plugin_textdomain( P2P_TEXTDOMAIN, '', basename( $base ) . '/lang' );

	require_once $base . '/core/util.php';
	require_once $base . '/core/api.php';
	require_once $base . '/autoload.php';

	P2P_Autoload::register( 'P2P_', $base . '/core' );

	P2P_Storage::init();

	P2P_Query_Post::init();
	P2P_Query_User::init();

	P2P_Widget::init();
	P2P_Shortcodes::init();

	if ( is_admin() )
		_p2p_load_admin();

	register_uninstall_hook( __FILE__, array( 'P2P_Storage', 'uninstall' ) );
}
scb_init( '_p2p_load' );

function _p2p_load_admin() {
	P2P_Autoload::register( 'P2P_', dirname( __FILE__ ) . '/admin' );

	new P2P_Box_Factory;
	new P2P_Column_Factory;
	new P2P_Dropdown_Factory;

	new P2P_Tools_Page;
}

function _p2p_init() {
	// Safe hook for calling p2p_register_connection_type()
	do_action( 'p2p_init' );
}
add_action( 'wp_loaded', '_p2p_init' );

