<?php

// Helper class for modifying the rewrite rules
abstract class scbRewrite {

	public function __construct( $plugin_file = '' ) {

		add_action( 'init', array( $this, 'generate' ) );
		add_action( 'generate_rewrite_rules', array( $this, 'generate' ) );

		if ( $plugin_file )
			scbUtil::add_activation_hook( $plugin_file, array( __CLASS__, 'flush' ) );
	}

	// This is where the actual code goes
	abstract public function generate();

	static public function flush() {
		global $wp_rewrite;

		$wp_rewrite->flush_rules();
	}
}

