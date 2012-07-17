<?php
/*
Plugin Name: Posts 2 Posts
Description: Create many-to-many relationships between all types of posts.
Version: 1.4-alpha2
Author: scribu
Author URI: http://scribu.net/
Plugin URI: http://scribu.net/wordpress/posts-to-posts
Text Domain: posts-to-posts
Domain Path: /lang
*/

define( 'P2P_PLUGIN_VERSION', '1.4-alpha2' );

define( 'P2P_TEXTDOMAIN', 'posts-to-posts' );

require dirname( __FILE__ ) . '/scb/load.php';

function _p2p_load() {
	$base = dirname( __FILE__ );

	load_plugin_textdomain( P2P_TEXTDOMAIN, '', basename( $base ) . '/lang' );

	_p2p_load_files( "$base/core", array(
		'storage', 'query', 'query-post', 'query-user', 'url-query',
		'util', 'item', 'list', 'side', 'type-factory', 'type', 'directed-type',
		'api', 'extra'
	) );

	P2P\Storage::init();

	P2P\WP_Query::init();
	P2P\User_Query::init();
	P2P\URL_Query::init();

	P2P\Widget::init();
	P2P\Shortcodes::init();

	if ( is_admin() ) {
		_p2p_load_files( "$base/admin", array(
			'mustache',
			'box-factory', 'box', 'fields',
			'column-factory', 'column',
			'tools'
		) );

		P2P\Mustache::init();
		P2P\Box_Factory::init();
		P2P\Column_Factory::init();

		new P2P\Tools_Page;
	}

	register_uninstall_hook( __FILE__, array( 'P2P\Storage', 'uninstall' ) );
}
_p2p_load();

function _p2p_init() {
	// Safe hook for calling p2p_register_connection_type()
	do_action( 'p2p_init' );
}
add_action( 'wp_loaded', '_p2p_init' );

function _p2p_load_files( $dir, $files ) {
	foreach ( $files as $file )
		require_once "$dir/$file.php";
}

