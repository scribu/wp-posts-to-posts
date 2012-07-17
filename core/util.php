<?php

/** @internal */
function _p2p_expand_direction( $direction ) {
	if ( 'any' == $direction )
		return array( 'from', 'to' );
	else
		return array( $direction );
}

/** @internal */
function _p2p_compress_direction( $directions ) {
	if ( empty( $directions ) )
		return false;

	if ( count( $directions ) > 1 )
		return 'any';

	return reset( $directions );
}

/** @internal */
function _p2p_flip_direction( $direction ) {
	$map = array(
		'from' => 'to',
		'to' => 'from',
		'any' => 'any',
	);

	return $map[ $direction ];
}

/** @internal */
function _p2p_normalize( $items ) {
	if ( !is_array( $items ) )
		$items = array( $items );

	foreach ( $items as &$item ) {
		if ( is_a( $item, 'P2P_Item' ) )
			$item = $item->get_id();
		elseif ( is_object( $item ) )
			$item = $item->ID;
	}

	return $items;
}

/** @internal */
function _p2p_wrap( $items, $class ) {
	foreach ( $items as &$item ) {
		$item = new $class( $item );
	}

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

