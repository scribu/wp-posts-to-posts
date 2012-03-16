<?php

/**
 * @internal
 */
abstract class P2P_Mustache {

	private static $loader;
	private static $mustache;

	public static function init() {
		if ( !class_exists( 'Mustache' ) )
			require dirname(__FILE__) . '/../mustache/Mustache.php';

		if ( !class_exists( 'MustacheLoader' ) )
			require dirname(__FILE__) . '/../mustache/MustacheLoader.php';

		self::$loader = new MustacheLoader( dirname(__FILE__) . '/templates', 'html' );

		self::$mustache = new Mustache( null, null, self::$loader );
	}

	public static function render( $template, $data ) {
		return self::$mustache->render( self::$loader[$template], $data );
	}
}

P2P_Mustache::init();

