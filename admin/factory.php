<?php

abstract class P2P_Factory {

	protected $queue = array();

	function register( $p2p_type, $args ) {
		if ( isset( $this->queue[$p2p_type] ) )
			return false;

		$args = (object) $args;

		if ( !$args->show )
			return false;

		$this->queue[$p2p_type] = $args;

		return true;
	}

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

	abstract function add_item( $directed, $object_type, $post_type, $title );
}

