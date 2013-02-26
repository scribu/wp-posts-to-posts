<?php

class P2P_Autoload {

	protected function __construct( $prefix, $basedir ) {
		$this->prefix = $prefix;
		$this->basedir = $basedir;
	}

	static function register( $prefix, $basedir ) {
		$loader = new self( $prefix, $basedir );

		spl_autoload_register( array( $loader, 'autoload' ) );
	}

	function autoload( $class ) {
		if ( $class[0] === '\\' ) {
			$class = substr( $class, 1 );
		}

		if ( strpos( $class, $this->prefix ) !== 0 ) {
			return;
		}

		$path = str_replace( $this->prefix, '', $class );
		$path = str_replace( '_', '-', strtolower( $path ) );

		$file = sprintf( '%s/%s.php', $this->basedir, $path );

		if ( is_file( $file ) ) {
			require $file;
		}
	}
}

