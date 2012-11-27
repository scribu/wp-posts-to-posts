<?php

abstract class P2P_Factory {

	protected $key;

	protected $queue = array();

	function __construct() {
		add_action( 'p2p_registered_connection_type', array( $this, 'check_ctype' ), 10, 2 );
	}

	// Check if a newly registered connection type needs an item to be produced.
	function check_ctype( $ctype, $args ) {
		$sub_args = $this->expand_arg( $args );

		if ( !$sub_args['show'] )
			return false;

		$this->queue[ $ctype->name ] = (object) $sub_args;
	}

	// Collect sub-args from main connection type args and set defaults
	protected function expand_arg( $args ) {
		if ( isset( $args[ $this->key ] ) ) {
			$sub_args = $args[ $this->key ];

			if ( !is_array( $sub_args ) ) {
				$sub_args = array( 'show' => $sub_args );
			}
		} else {
			$sub_args = array( 'show' => false );
		}

		$sub_args = wp_parse_args( $sub_args, array(
			'show' => 'any',
		) );

		return $sub_args;
	}

	// Begin processing item queue for a particular screen.
	function add_items() {
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

	// Filter item queue based on object type.
	function filter( $object_type, $post_type ) {
		foreach ( $this->queue as $p2p_type => $args ) {
			$ctype = p2p_type( $p2p_type );

			$directions = self::determine_directions( $ctype, $object_type, $post_type, $args->show );

			$title = self::get_title( $directions, $ctype );

			foreach ( $directions as $direction ) {
				$key = ( 'to' == $direction ) ? 'to' : 'from';

				$directed = $ctype->set_direction( $direction );

				$this->add_item( $directed, $object_type, $post_type, $title[$key] );
			}
		}
	}

	// Produce an item and add it to the screen.
	abstract function add_item( $directed, $object_type, $post_type, $title );

	protected static function get_title( $directions, $ctype ) {
		$title = array(
			'from' => $ctype->get_field( 'title', 'from' ),
			'to' => $ctype->get_field( 'title', 'to' )
		);

		if ( count( $directions ) > 1 && $title['from'] == $title['to'] ) {
			$title['from'] .= __( ' (from)', P2P_TEXTDOMAIN );
			$title['to']   .= __( ' (to)', P2P_TEXTDOMAIN );
		}

		return $title;
	}

	protected static function determine_directions( $ctype, $object_type, $post_type, $show_ui ) {
		$direction = $ctype->direction_from_types( $object_type, $post_type );
		if ( !$direction )
			return array();

		return $ctype->_directions_for_admin( $direction, $show_ui );
	}
}

