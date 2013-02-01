<?php

function p2p_list_cluster( $items, $callback ) {
	$groups = array();

	foreach ( $items as $item ) {
		$key = $callback( $item );

		if ( null === $key )
			continue;

		$groups[ $key ][] = $item;
	}

	return $groups;
}

/** @internal */
function _p2p_expand_direction( $direction ) {
	if ( !$direction )
		return array();

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
function _p2p_extract_post_types( $sides ) {
	$ptypes = array();

	foreach ( $sides as $side ) {
		if ( 'post' == $side->get_object_type() )
			_p2p_append( $ptypes, $side->query_vars['post_type'] );
	}

	return array_unique( $ptypes );
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

/** @internal */
function _p2p_get_other_id( $item ) {
	if ( $item->ID == $item->p2p_from )
		return $item->p2p_to;

	if ( $item->ID == $item->p2p_to )
		return $item->p2p_from;

	trigger_error( "Corrupted data for item $inner_item->ID", E_USER_WARNING );
}

/** @internal */
function _p2p_get_list( $args ) {
	$ctype = p2p_type( $args['ctype'] );
	if ( !$ctype ) {
		trigger_error( sprintf( "Unregistered connection type '%s'.", $ctype ), E_USER_WARNING );
		return '';
	}

	$directed = $ctype->find_direction( $args['item'] );
	if ( !$directed )
		return '';

	$context = $args['context'];

	$extra_qv = array(
		'p2p:per_page' => -1,
		'p2p:context' => $context
	);

	$connected = call_user_func( array( $directed, $args['method'] ), $args['item'], $extra_qv, 'abstract' );

	switch ( $args['mode'] ) {
	case 'inline':
		$render_args = array(
			'separator' => ', '
		);
		break;

	case 'ol':
		$render_args = array(
			'before_list' => '<ol id="' . $ctype->name . '_list">',
			'after_list' => '</ol>',
		);
		break;

	case 'ul':
	default:
		$render_args = array(
			'before_list' => '<ul id="' . $ctype->name . '_list">',
			'after_list' => '</ul>',
		);
		break;
	}

	$render_args['echo'] = false;

	return apply_filters( "p2p_{$context}_html", $connected->render( $render_args ), $connected, $directed, $args['mode'] );
}

