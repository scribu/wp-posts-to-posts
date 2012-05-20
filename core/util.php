<?php

/** @internal */
function _p2p_expand_direction( $direction ) {
	if ( 'any' == $direction )
		return array( 'from', 'to' );
	else
		return array( $direction );
}

/** @internal */
function _p2p_normalize( $items ) {
	if ( !is_array( $items ) )
		$items = array( $items );

	if ( is_object( reset( $items ) ) )
		$items = wp_list_pluck( $items, 'ID' );

	return $items;
}

/** @internal */
function _p2p_meta_sql_helper( $query ) {
	global $wpdb;

	if ( isset( $query[0] ) ) {
		$meta_query = $query;
	}
	else {
		$meta_query = array();

		foreach ( $query as $key => $value ) {
			$meta_query[] = compact( 'key', 'value' );
		}
	}

	return get_meta_sql( $meta_query, 'p2p', $wpdb->p2p, 'p2p_id' );
}

/** @internal */
function _p2p_pluck( &$arr, $key ) {
	$value = $arr[ $key ];
	unset( $arr[ $key ] );
	return $value;
}

/** @internal */
function _p2p_append( &$arr, $values ) {
	$arr = array_merge( $arr, $values );
}

/** @internal */
function _p2p_first( $args ) {
	if ( empty( $args ) )
		return false;

	return reset( $args );
}

