<?php

abstract class P2P_Factory {

	static function filter( $ctype, $object_type, $post_type, $show_ui ) {
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
}

