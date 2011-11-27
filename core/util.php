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

	static function expand_direction( $direction ) {
		if ( 'any' == $direction )
			return array( 'from', 'to' );
		else
			return array( $direction );
	}

	static function choose_side( $current, $from, $to ) {
		if ( $from == $to && $current == $from )
			return 'any';

		if ( $current == $from )
			return 'to';

		if ( $current == $to )
			return 'from';

		return false;
	}
}

/**
 * @internal
 */
function _p2p_meta_sql_helper( $data ) {
	global $wpdb;

	if ( isset( $data[0] ) ) {
		$meta_query = $data;
	}
	else {
		$meta_query = array();

		foreach ( $data as $key => $value ) {
			$meta_query[] = compact( 'key', 'value' );
		}
	}

	return get_meta_sql( $meta_query, 'p2p', $wpdb->p2p, 'p2p_id' );
}

/**
 * @internal
 */
function _p2p_pluck( &$arr, $key ) {
	$value = $arr[ $key ];
	unset( $arr[ $key ] );
	return $value;
}

function _p2p_append( &$arr, $values ) {
	$arr = array_merge( $arr, $values );
}

/**
 * @internal
 */
function _p2p_get_field_type( $args ) {
	if ( isset( $args['type'] ) )
		return $args['type'];

	if ( isset( $args['values'] ) && is_array( $args['values'] ) )
		return 'select';

	return 'text';
}

