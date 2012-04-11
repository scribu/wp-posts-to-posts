<?php

class P2P_Query {

	static function expand_shortcut_qv( &$q ) {
		$qv_map = array(
			'connected' => 'any',
			'connected_to' => 'to',
			'connected_from' => 'from',
		);

		foreach ( $qv_map as $key => $direction ) {
			if ( !empty( $q[ $key ] ) ) {
				$q['connected_items'] = _p2p_pluck( $q, $key );
				$q['connected_direction'] = $direction;
			}
		}
	}

	static function get_qv( $q ) {
		if ( !isset( $q['p2p_type'] ) ) {
			if ( isset( $q['connected_items'] ) ) {
				trigger_error( "P2P queries without 'connected_type' are no longer supported." );
			}
			return false;
		}

		$qv['p2p_type'] = $q['p2p_type'];

		$qv_list = array(
			'items', 'direction', 'meta',
			'orderby', 'order_num', 'order'
		);

		foreach ( $qv_list as $key ) {
			$qv[$key] = isset( $q["connected_$key"] ) ?  $q["connected_$key"] : false;
		}

		return $qv;
	}

	/**
	 * Sets 'p2p_type' => array( connection_type => direction )
	 *
	 * @return:
	 * null means ignore current query
	 * false means trigger 404
	 * true means proceed
	 */
	static function expand_connected_type( &$q, $item, $object_type ) {
		if ( !isset( $q['connected_type'] ) )
			return;

		$ctypes = (array) _p2p_pluck( $q, 'connected_type' );

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

			$p2p_types[ $p2p_type ] = $directed->get_direction();
		}

		if ( empty( $p2p_types ) )
			return false;

		if ( 1 == count( $p2p_types ) )
			$q = $directed->get_connected_args( $q );
		else
			$q['p2p_type'] = $p2p_types;

		return true;
	}

	static function alter_clauses( $clauses, $q, $main_id_column ) {
		global $wpdb;

		$clauses['fields'] .= ", $wpdb->p2p.*";

		$clauses['join'] .= " INNER JOIN $wpdb->p2p";

		// Handle main query
		if ( 'any' == $q['items'] ) {
			$search = false;
		} else {
			$search = implode( ',', array_map( 'absint', _p2p_normalize( $q['items'] ) ) );
		}

		$where_parts = array();

		foreach ( $q['p2p_type'] as $p2p_type => $direction ) {
			if ( 0 === $p2p_type ) // used by migration script
				$part = "1 = 1";
			else
				$part = $wpdb->prepare( "$wpdb->p2p.p2p_type = %s", $p2p_type );

			$fields = array( 'p2p_from', 'p2p_to' );

			switch ( $direction ) {

			case 'from':
				$fields = array_reverse( $fields );
				// fallthrough
			case 'to':
				list( $from, $to ) = $fields;

				$part .= " AND $main_id_column = $wpdb->p2p.$from";

				if ( $search ) {
					$part .= " AND $wpdb->p2p.$to IN ($search)";
				}

				break;
			default:
				if ( $search ) {
					$part .= " AND (
						($main_id_column = $wpdb->p2p.p2p_to AND $wpdb->p2p.p2p_from IN ($search)) OR
						($main_id_column = $wpdb->p2p.p2p_from AND $wpdb->p2p.p2p_to IN ($search))
					)";
				} else {
					$part .= " AND ($main_id_column = $wpdb->p2p.p2p_to OR $main_id_column = $wpdb->p2p.p2p_from)";
				}
			}

			$where_parts[] = '(' . $part . ')';
		}

		if ( 1 == count( $where_parts ) )
			$clauses['where'] .= " AND " . $where_parts[0];
		else
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

	private function find_direction( $ctype, $arg, $object_type ) {
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

