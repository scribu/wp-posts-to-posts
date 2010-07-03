<?php

$GLOBALS['_scb_data'] = array( 21, __FILE__, array( 'scbUtil', 'scbOptions', 'scbForms', 'scbTable',
	'scbWidget', 'scbAdminPage', 'scbBoxesPage',
	'scbQuery', 'scbRewrite', 'scbCron', ) );

if ( !class_exists( 'scbLoad4' ) ) :
class scbLoad4 {

	private static $candidates;
	private static $classes;
	private static $callbacks = array();
	
	private static $loaded;

	static function init( $callback = '' ) {
		list( $rev, $file, $classes ) = $GLOBALS['_scb_data'];

		self::$candidates[$file] = $rev;
		self::$classes[$file] = $classes;

		if ( !empty( $callback ) ) {
			self::$callbacks[$file] = $callback;

			add_action( 'activated_plugin',  array( __CLASS__, 'delayed_activation' ) );
		}

		// TODO: don't load when activating a plugin ?
		add_action( 'plugins_loaded', array( __CLASS__, 'load' ), 10, 0 );
	}

	static function delayed_activation( $plugin ) {
		$plugin_dir = dirname( $plugin );

		if ( '.' == $plugin_dir )
			return;

		$found = false;
		foreach ( self::$callbacks as $file => $callback )
			if ( plugin_basename( dirname( dirname( $file ) ) ) == $plugin_dir ) {
				$found = true;
				break;
			}
		if ( !$found )
			return;

		self::load( false );
		call_user_func( $callback );

		if ( is_plugin_active_for_network( $plugin ) ) {
			// network wide activation
			foreach ( self::get_active_blogs() as $blog_id ) {
				switch_to_blog( $blog_id );
				do_action( 'scb_activation_' . $plugin );
			}
			restore_current_blog();
		}
		else {
			// regular activation
			do_action( 'scb_activation_' . $plugin );
		}
	}

	// TODO: bail activation if more than X blogs
	private static function get_active_blogs() {
		global $wpdb;

		return $wpdb->get_col( "
			SELECT blog_id
			FROM $wpdb->blogs
			WHERE site_id = '{$wpdb->siteid}'
			AND deleted = 0
		" );
	}

	static function load( $do_callbacks = true ) {
		arsort( self::$candidates );

		$file = key( self::$candidates );

		$path = dirname( $file ) . '/';

		foreach ( self::$classes[$file] as $class_name ) {
			if ( class_exists( $class_name ) )
				continue;
			
			$fpath = $path . substr( $class_name, 3 ) . '.php';
			if ( file_exists( $fpath ) ) {
				include $fpath;
				self::$loaded[] = $fpath;
			}
		}

		if ( $do_callbacks )
			foreach ( self::$callbacks as $callback )
				call_user_func( $callback );
	}

	static function get_info() {
		arsort( self::$candidates );

		return array( self::$loaded, self::$candidates );
	}
}
endif;

if ( !function_exists( 'scb_init' ) ) :
function scb_init( $callback = '' ) {
	scbLoad4::init( $callback );
}
endif;

