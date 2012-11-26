<?php

class P2P_Column_Factory extends P2P_Factory {

	function __construct() {
		parent::__construct();

		add_action( 'admin_print_styles', array( $this, 'add_items' ) );
	}

	function check_ctype( $ctype, $args ) {
		if ( isset( $args['admin_column'] ) ) {
			$column_args = _p2p_pluck( $args, 'admin_column' );
			if ( !is_array( $column_args ) )
				$column_args = array( 'show' => $column_args );
		} else {
			$column_args = array();
		}

		$column_args = wp_parse_args( $column_args, array(
			'show' => false,
		) );

		$this->register( $ctype->name, $column_args );
	}

	function add_item( $directed, $object_type, $post_type, $title ) {
		$class = 'P2P_Column_' . ucfirst( $object_type );
		$column = new $class( $directed );

		$column->styles();
	}
}

new P2P_Column_Factory;

