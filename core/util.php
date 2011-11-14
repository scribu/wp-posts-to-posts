<?php

/**
 * Various utilities for working with connection types.
 *
 * They come wit no backwards-compatibility warantee.
 */
abstract class P2P_Util {

	/**
	 * Attempt to find a post type.
	 *
	 * @param mixed A post type, a post id, a post object, an array of post ids or of objects.
	 *
	 * @return bool|string False on failure, post type on success.
	 */
	static function find_post_type( $arg ) {
		if ( is_array( $arg ) ) {
			$arg = reset( $arg );
		}

		if ( is_object( $arg ) ) {
			$post_type = $arg->post_type;
		} elseif ( $post_id = (int) $arg ) {
			$post = get_post( $post_id );
			if ( !$post )
				return false;
			$post_type = $post->post_type;
		} else {
			$post_type = $arg;
		}

		if ( !post_type_exists( $post_type ) )
			return false;

		return $post_type;
	}

	static function get_first_valid_ptype( $post_types ) {
		do {
			$ptype = get_post_type_object( array_shift( $post_types ) );
		} while ( !$ptype && !empty( $post_types ) );

		return $ptype;
	}

	/**
	 * Check if a certain post or post type could have connections of this type.
	 *
	 * @param string A post type to check against.
	 * @param array List of post types (from).
	 * @param array List of post types (to).
	 *
	 * @return bool|string False on failure, direction on success.
	 */
	static function get_direction( $post_type, $from, $to ) {
		if ( in_array( $post_type, $from ) ) {
			$direction = 'from';
		} elseif ( in_array( $post_type, $to ) ) {
			$direction = 'to';
		} else {
			$direction = false;
		}

		return $direction;
	}

	/**
	 * @param string The direction in which ordering is allowed
	 * @param string The current direction
	 *
	 * @return bool|string False on failure, the connection field key otherwise
	 */
	static function get_orderby_key( $ordering_direction, $connection_direction ) {
		if ( !$ordering_direction || 'any' == $connection_direction )
			return false;

		if ( 'any' == $ordering_direction || $connection_direction == $ordering_direction )
			return '_order_' . $connection_direction;

		if ( 'from' == $connection_direction )
			return $ordering_direction;

		return false;
	}

	static function get_ptype_label( $ptypes ) {
		return get_post_type_object( $ptypes[0] )->labels->name;
	}

	static function expand_title( $title, $from, $to ) {
		if ( !$title )
			$title = array();

		if ( $title && !is_array( $title ) ) {
			return array(
				'from' => $title,
				'to' => $title,
			);
		}

		foreach ( array( 'from', 'to' ) as $key ) {
			if ( isset( $title[$key] ) )
				continue;

			$other_key = ( 'from' == $key ) ? 'to' : 'from';

			$title[$key] = sprintf(
				__( 'Connected %s', P2P_TEXTDOMAIN ),
				P2P_Util::get_ptype_label( $$other_key )
			);
		}

		return $title;
	}
}

