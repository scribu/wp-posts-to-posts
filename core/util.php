<?php

/** @internal */
function _p2p_expand_direction( $direction ) {
	if ( 'any' == $direction )
		return array( 'from', 'to' );
	else
		return array( $direction );
}

/** @internal */
function _p2p_get_ids( $items ) {
	if ( !is_array( $items ) )
		$items = array( $items );

	if ( is_object( reset( $items ) ) )
		$items = wp_list_pluck( $items, 'ID' );

	return $items;
}

/** @internal */
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
function _p2p_get_field_type( $args ) {
	if ( isset( $args['type'] ) )
		return $args['type'];

	if ( isset( $args['values'] ) && is_array( $args['values'] ) )
		return 'select';

	return 'text';
}

/** @internal */
function _p2p_first( $args ) {
	if ( empty( $args ) )
		return false;

	return reset( $args );
}

