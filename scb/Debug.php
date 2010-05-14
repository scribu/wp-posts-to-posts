<?php

class scbDebug {
	private $args;

	function __construct($args) {
		$this->args = $args;

		register_shutdown_function(array($this, '_delayed'));
	}

	function _delayed() {
		if ( !current_user_can('administrator') )
			return;

		$this->raw($this->args);
	}

	static function raw($args) {
		$args = scbUtil::array_map_recursive('esc_html', $args);

		echo "<pre>";
		foreach ( $args as $arg )
			if ( is_array($arg) || is_object($arg) )
				print_r($arg);
			else
				var_dump($arg);
		echo "</pre>";	
	}

	static function info() {
		self::raw(scbLoad4::get_info());
	}
}


if ( ! function_exists('debug') ):
function debug() {
	$args = func_get_args();

	scbDebug::raw($args);
}
endif;


if ( ! function_exists('debug_fb') ):
function debug_fb() {
	$args = func_get_args();

	// integrate with FirePHP
	if ( class_exists('FirePHP') ) {
		$firephp = FirePHP::getInstance(true);
		$firephp->group('debug');
		foreach ( $args as $arg )
			$firephp->log($arg);
		$firephp->groupEnd();

		return;
	}

	new scbDebug($args);
}
endif;

if ( ! function_exists('scb_debug') ):
function scb_debug() {
	add_action('shutdown', array('scbDebug', 'info'));
}
endif;

