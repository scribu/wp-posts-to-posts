<?php
/*
Plugin Name: Posts 2 Posts
Description: Create many-to-many relationships between all types of posts.
Version: 1.6.4
Author: scribu
Author URI: http://scribu.net/
Plugin URI: http://scribu.net/wordpress/posts-to-posts
Text Domain: posts-to-posts
Domain Path: /lang
*/

define( 'P2P_PLUGIN_VERSION', '1.6.4' );

define( 'P2P_TEXTDOMAIN', 'posts-to-posts' );

function _p2p_load() {
	load_plugin_textdomain( P2P_TEXTDOMAIN, '', basename( dirname( __FILE__ ) ) . '/lang' );

	if ( !function_exists( 'p2p_register_connection_type' ) ) {
		require_once dirname( __FILE__ ) . '/vendor/scribu/lib-posts-to-posts/autoload.php';
	}

	P2P_Storage::init();

	P2P_Query_Post::init();
	P2P_Query_User::init();

	P2P_URL_Query::init();

	P2P_Widget::init();
	P2P_Shortcodes::init();

	register_uninstall_hook( __FILE__, array( 'P2P_Storage', 'uninstall' ) );

	if ( is_admin() )
		_p2p_load_admin();
}

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

if ( is_dir( dirname( __FILE__ ) . '/vendor' ) ) {
	// Not using vendor/autload.php because scb-framework/load.php has better compatibility

	require_once dirname( __FILE__ ) . '/vendor/mustache/mustache/src/Mustache/Autoloader.php';
	Mustache_Autoloader::register();

	require_once dirname( __FILE__ ) . '/vendor/scribu/scb-framework/load.php';
}

scb_init( '_p2p_load' );
add_action( 'wp_loaded', '_p2p_init' );

