<?php

class P2P_Query {

	/**
	 * Create instance from mixed query vars
	 *
	 * @param array Query vars to collect parameters from
	 * @return:
	 * - null means ignore current query
	 * - WP_Error instance if the query is invalid
	 * - P2P_Query instance on success
	 */
	public static function create_from_qv( $q, $object_type ) {
		$shortcuts = array(
			'connected' => 'any',
			'connected_to' => 'to',
			'connected_from' => 'from',
		);

		foreach ( $shortcuts as $key => $direction ) {
			if ( !empty( $q[ $key ] ) ) {
				$q['connected_items'] = _p2p_pluck( $q, $key );
				$q['connected_direction'] = $direction;
			}
		}

		if ( !isset( $q['connected_type'] ) ) {
			if ( isset( $q['connected_items'] ) ) {
				return new WP_Error( "Queries without 'connected_type' are no longer supported." );
			}

			return;
		}

		$ctypes = (array) _p2p_pluck( $q, 'connected_type' );

		$item = isset( $q['connected_items'] ) ? $q['connected_items'] : 'any';

		if ( isset( $q['connected_direction'] ) )
			$directions = (array) _p2p_pluck( $q, 'connected_direction' );
		else
			$directions = array();

		$p2p_types = array();

		foreach ( $ctypes as $i => $p2p_type ) {
			$ctype = p2p_type( $p2p_type );

			if ( !$ctype )
				continue;

			if ( isset( $directions[$i] ) ) {
				$directed = $ctype->set_direction( $directions[$i] );
			} else {
				$directed = self::find_direction( $ctype, $item, $object_type );
			}

			if ( !$directed )
				continue;

			$p2p_types[] = $directed;
		}

		if ( empty( $p2p_types ) )
			return new WP_Error( "Coud not find direction(s)." );

		if ( 1 == count( $p2p_types ) ) {
			$directed = $p2p_types[0];

			if ( $orderby_key = $directed->get_orderby_key() ) {
				$q = wp_parse_args( $q, array(
					'connected_orderby' => $orderby_key,
					'connected_order' => 'ASC',
					'connected_order_num' => true,
				) );
			}

			$q = array_merge_recursive( $q, array(
				'connected_meta' => $directed->data
			) );
		}

		return new P2P_Query( $p2p_types, self::get_qv( $q ) );
	}

	protected static function get_qv( $q ) {
		$qv_list = array(
			'items', 'meta',
			'orderby', 'order_num', 'order'
		);

		foreach ( $qv_list as $key ) {
			$qv[$key] = isset( $q["connected_$key"] ) ? $q["connected_$key"] : false;
		}

		if ( !isset( $q['connected_query'] ) )
			$qv['query'] = array();

		return $qv;
	}

	private function __construct( $ctypes, $args ) {
		$this->ctypes = $ctypes;
		$this->args = $args;
	}

	/**
	 * For high-level query modifications
	 */
	public function alter_qv( &$q ) {
		$q = wp_parse_args( $q, array(
			'p2p:context' => false
		) );

		$q = $this->ctypes[0]->get_opposite( 'side' )->get_base_qv( $q );

		if ( 1 == count( $this->ctypes ) ) {
			$q = apply_filters( 'p2p_connected_args', $q, $this->ctypes[0], $this->args['items'] );
		}
	}

	private function do_other_query() {
		$qv = $this->args['query'];

		_p2p_append( $qv, array(
			'fields' => 'ids',
			'p2p:include' => _p2p_normalize( $this->args['items'] ),
			'p2p:per_page' => -1
		) );

		$side = $this->ctypes[0]->get_current( 'side' );

		return $side->capture_query( $side->get_base_qv( $side->translate_qv( $qv ) ) );
	}

	/**
	 * For low-level query modifications
	 */
	public function alter_clauses( &$clauses, $main_id_column ) {
		global $wpdb;

		$q = $this->args;

		$clauses['fields'] .= ", $wpdb->p2p.*";

		$clauses['join'] .= " INNER JOIN $wpdb->p2p";

		$search = $this->do_other_query();

		$where_parts = array();

		foreach ( $this->ctypes as $directed ) {
			if ( null === $directed ) // used by migration script
				continue;

			$part = $wpdb->prepare( "$wpdb->p2p.p2p_type = %s", $directed->name );

			$fields = array( 'p2p_from', 'p2p_to' );

			switch ( $directed->get_direction() ) {

			case 'from':
				$fields = array_reverse( $fields );
				// fallthrough
			case 'to':
				list( $from, $to ) = $fields;

				$part .= " AND $main_id_column = $wpdb->p2p.$from";
				$part .= " AND $wpdb->p2p.$to IN ($search)";

				break;
			default:
				$part .= " AND (
					($main_id_column = $wpdb->p2p.p2p_to AND $wpdb->p2p.p2p_from IN ($search)) OR
					($main_id_column = $wpdb->p2p.p2p_from AND $wpdb->p2p.p2p_to IN ($search))
				)";
			}

			$where_parts[] = '(' . $part . ')';
		}

		if ( 1 == count( $where_parts ) )
			$clauses['where'] .= " AND " . $where_parts[0];
		elseif ( !empty( $where_parts ) )
			$clauses['where'] .= " AND (" . implode( ' OR ', $where_parts ) . ")";

		// Handle custom fields
		if ( !empty( $q['meta'] ) ) {
			$meta_clauses = _p2p_meta_sql_helper( $q['meta'] );
			foreach ( $meta_clauses as $key => $value ) {
				$clauses[ $key ] .= $value;
			}
		}

		// Handle ordering
		if ( $q['orderby'] ) {
			$clauses['join'] .= $wpdb->prepare( "
				LEFT JOIN $wpdb->p2pmeta AS p2pm_order ON (
					$wpdb->p2p.p2p_id = p2pm_order.p2p_id AND p2pm_order.meta_key = %s
				)
			", $q['orderby'] );

			$order = ( 'DESC' == strtoupper( $q['order'] ) ) ? 'DESC' : 'ASC';

			$field = 'meta_value';

			if ( $q['order_num'] )
				$field .= '+0';

			$clauses['orderby'] = "p2pm_order.$field $order";
		}

		return $clauses;
	}

	private static function find_direction( $ctype, $arg, $object_type ) {
		$opposite_side = self::choose_side( $object_type,
			$ctype->object['from'],
			$ctype->object['to']
		);

		if ( in_array( $opposite_side, array( 'from', 'to' ) ) )
			return $ctype->set_direction( $opposite_side );

		return $ctype->find_direction( $arg );
	}

	private static function choose_side( $current, $from, $to ) {
		if ( $from == $to && $current == $from )
			return 'any';

		if ( $current == $from )
			return 'to';

		if ( $current == $to )
			return 'from';

		return false;
	}
}

