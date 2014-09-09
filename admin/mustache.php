<?php

/**
 * @internal
 */
abstract class P2P_Mustache {

	private static $mustache;

	public static function init() {
		$loader = new Mustache_Loader_FilesystemLoader(
			dirname(__FILE__) . '/templates', array( 'extension' => 'html' ) );

		self::$mustache = new Mustache_Engine( array(
			'loader' => $loader,
			'partials_loader' => $loader
		) );
	}

	public static function render( $template, $data ) {
		return self::$mustache->render( $template, $data );
	}
}

