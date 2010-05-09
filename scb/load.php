<?php
if ( !class_exists('scbLoad3') ) :
class scbLoad3 {

	private static $candidates;
	private static $loaded;
	private static $initial_load;

	static function init($rev, $file, $classes) {
		if ( $path = get_option('scb-framework') && !self::$initial_load ) {
			if ( $path != __FILE__ )
				include $path;

			self::$initial_load = true;
		}

		self::$candidates[$file] = $rev;

		self::load(dirname($file) . '/', $classes);

		add_action('deactivate_plugin', array(__CLASS__, 'deactivate'));
		add_action('update_option_active_plugins', array(__CLASS__, 'reorder'));
	}

	static function deactivate($plugin) {
		$plugin = dirname($plugin);

		if ( '.' == $plugin )
			return;

		foreach ( self::$candidates as $path => $rev )
			if ( plugin_basename(dirname(dirname($path))) == $plugin )
				unset(self::$candidates[$path]);
	}

	static function reorder() {
		arsort(self::$candidates);

		update_option('scb-framework', key(self::$candidates));
	}

	private static function load($path, $classes) {
		foreach ( $classes as $class_name ) {
			if ( class_exists($class_name) )
				continue;

			$fpath = $path . substr($class_name, 3) . '.php';

			if ( file_exists($fpath) ) {
				self::$loaded[$class_name] = $fpath;
				include $fpath;
			}
		}
	}

	static function get_info() {
		arsort(self::$candidates);

		return array(get_option('scb-framework'), self::$loaded, self::$candidates);
	}
}
endif;

scbLoad3::init(14, __FILE__, array(
	'scbUtil', 'scbOptions', 'scbForms', 'scbTable', 'scbDebug',
	'scbWidget', 'scbAdminPage', 'scbBoxesPage',
	'scbQuery', 'scbRewrite', 'scbCron',
));

