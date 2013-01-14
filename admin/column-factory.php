<?php

class P2P_Column_Factory extends P2P_Factory {

	protected $key = 'admin_column';

	function __construct() {
		parent::__construct();

		add_action( 'load-edit.php', array( $this, 'add_items' ) );
		add_action( 'load-users.php', array( $this, 'add_items' ) );
	}

	function add_item( $directed, $object_type, $post_type, $title ) {
		$class = 'P2P_Column_' . ucfirst( $object_type );
		$column = new $class( $directed );

		add_action( 'admin_print_styles', array( $column, 'styles' ) );
	}
}

