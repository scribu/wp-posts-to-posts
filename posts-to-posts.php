<?php
/*
Plugin Name: Posts 2 Posts
Description: Create many-to-many relationships between all types of posts.
Version: 1.6.2-alpha
Author: scribu
Author URI: http://scribu.net/
Plugin URI: http://scribu.net/wordpress/posts-to-posts
Text Domain: posts-to-posts
Domain Path: /lang
*/

define( 'P2P_PLUGIN_VERSION', '1.6.1' );

define( 'P2P_TEXTDOMAIN', 'posts-to-posts' );

if ( is_readable( dirname( __FILE__ ) . '/vendor' ) ) {
	// It's a root package, so we need to handle dependency loading ourselves
	require_once dirname( __FILE__ ) . '/vendor/mustache/mustache/src/Mustache/Autoloader.php';
	Mustache_Autoloader::register();

	// Not using vendor/autload.php because scb-framework/load.php has better compatibility
	require_once dirname( __FILE__ ) . '/vendor/scribu/scb-framework/load.php';
}

function _p2p_load() {
	load_plugin_textdomain( P2P_TEXTDOMAIN, '', basename( dirname( __FILE__ ) ) . '/lang' );

	require_once dirname( __FILE__ ) . '/core/init.php';

	register_uninstall_hook( __FILE__, array( 'P2P_Storage', 'uninstall' ) );

	if ( is_admin() )
		_p2p_load_admin();
}
scb_init( '_p2p_load' );

function _p2p_load_admin() {
	P2P_Autoload::register( 'P2P_', dirname( __FILE__ ) . '/admin' );

	P2P_Mustache::init();

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

