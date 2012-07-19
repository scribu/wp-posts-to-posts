<?php

class P2P_Column_Factory extends P2P_Factory {

	function __construct() {
		add_filter( 'p2p_connection_type_args', array( $this, 'filter_ctypes' ) );

		add_action( 'admin_print_styles', array( $this, 'add_columns' ) );
	}

	function filter_ctypes( $args ) {
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

		$this->register( $args['name'], $column_args );

		return $args;
	}

	function add_columns() {
		$screen = get_current_screen();

		$screen_map = array(
			'edit' => 'post',
			'users' => 'user'
		);

		if ( !isset( $screen_map[ $screen->base ] ) )
			return;

		$object_type = $screen_map[ $screen->base ];

		$this->filter( $object_type, $screen->post_type );
	}

	function add_item( $directed, $object_type, $post_type, $title ) {
		$class = 'P2P_Column_' . ucfirst( $object_type );
		$column = new $class( $directed );

		$column->styles();
	}
}

new P2P_Column_Factory;

