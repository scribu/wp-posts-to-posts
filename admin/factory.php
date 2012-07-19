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

			$title = $ctype->title;

			if ( count( $directions ) > 1 && $title['from'] == $title['to'] ) {
				$title['from'] .= __( ' (from)', P2P_TEXTDOMAIN );
				$title['to']   .= __( ' (to)', P2P_TEXTDOMAIN );
			}

			foreach ( $directions as $direction ) {
				$key = ( 'to' == $direction ) ? 'to' : 'from';

				$directed = $ctype->set_direction( $direction );

				$this->add_item( $directed, $object_type, $post_type, $title[$key] );
			}
		}
	}

	protected static function determine_directions( $ctype, $object_type, $post_type, $show_ui ) {
		$direction = $ctype->direction_from_types( $object_type, $post_type );
		if ( !$direction )
			return array();

		if ( $ctype->indeterminate )
			$direction = 'any';

		if ( $ctype->reciprocal ) {
			if ( $show_ui )
				$directions = array( 'any' );
			else
				$directions = array();
		} else {
			$directions = array_intersect(
				_p2p_expand_direction( $show_ui ),
				_p2p_expand_direction( $direction )
			);
		}

		return $directions;
	}

	abstract function add_item( $directed, $object_type, $post_type, $title );
}

