<?php

class P2P_Query {

	protected $ctypes, $items, $query, $meta;
	protected $orderby, $order, $order_num;

	private static function expand_shortcuts( $q ) {
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

		return $q;
	}

	private static function expand_ctypes( $item, $directions, $object_type, $ctypes ) {
		$p2p_types = array();

		foreach ( $ctypes as $i => $p2p_type ) {
			$ctype = p2p_type( $p2p_type );

			if ( !$ctype )
				continue;

			if ( isset( $directions[ $i ] ) ) {
				$directed = $ctype->set_direction( $directions[ $i ] );
			} else {
				$directed = $ctype->find_direction( $item, true, $object_type );
			}

			if ( !$directed )
				continue;

			$p2p_types[] = $directed;
		}

		return $p2p_types;
	}

	private static function finalize_query_vars( $q, $directed, $item ) {
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

		$q = $directed->get_final_qv( $q, 'opposite' );

		return apply_filters( 'p2p_connected_args', $q, $directed, $item );
	}

	/**
	 * Create instance from mixed query vars; also returns the modified query vars.
	 *
	 * @param array Query vars to collect parameters from
	 * @return:
	 * - null means ignore current query
	 * - WP_Error instance if the query is invalid
	 * - array( P2P_Query, array )
	 */
	public static function create_from_qv( $q, $object_type ) {
		$q = self::expand_shortcuts( $q );

		if ( !isset( $q['connected_type'] ) ) {
			if ( isset( $q['connected_items'] ) ) {
				return new WP_Error( 'no_connection_type', "Queries without 'connected_type' are no longer supported." );
			}

			return;
		}

		if ( isset( $q['connected_direction'] ) )
			$directions = (array) $q['connected_direction'];
		else
			$directions = array();

		$item = isset( $q['connected_items'] ) ? $q['connected_items'] : 'any';

		$ctypes = (array) $q['connected_type'];

		$p2p_types = self::expand_ctypes( $item, $directions, $object_type, $ctypes );

		if ( empty( $p2p_types ) )
			return new WP_Error( 'no_direction', "Could not find direction(s)." );

		if ( 1 == count( $p2p_types ) ) {
			$q = self::finalize_query_vars( $q, $p2p_types[0], $item );
		}

		$p2p_q = new P2P_Query;

		$p2p_q->ctypes = $p2p_types;
		$p2p_q->items = $item;

		foreach ( array( 'meta', 'orderby', 'order_num', 'order' ) as $key ) {
			$p2p_q->$key = isset( $q["connected_$key"] ) ? $q["connected_$key"] : false;
		}

		$p2p_q->query = isset( $q['connected_query'] ) ? $q['connected_query'] : array();

		return array( $p2p_q, $q );
	}

	protected function __construct() {}

	public function __get( $key ) {
		return $this->$key;
	}

	private function do_other_query( $directed, $which ) {
		$side = $directed->get( $which, 'side' );

		$qv = array_merge( $this->query, array(
			'fields' => 'ids',
			'p2p:per_page' => -1
		) );

		if ( 'any' != $this->items )
			$qv['p2p:include'] = _p2p_normalize( $this->items );

		$qv = $directed->get_final_qv( $qv, $which );

		return $side->capture_query( $qv );
	}

	/**
	 * For low-level query modifications
	 */
	public function alter_clauses( &$clauses, $main_id_column ) {
		global $wpdb;

		$clauses['fields'] .= ", $wpdb->p2p.*";

		$clauses['join'] .= " INNER JOIN $wpdb->p2p";

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

				$search = $this->do_other_query( $directed, 'current' );

				$part .= " AND $main_id_column = $wpdb->p2p.$from";
				$part .= " AND $wpdb->p2p.$to IN ($search)";

				break;
			default:
				$part .= sprintf ( " AND (
					($main_id_column = $wpdb->p2p.p2p_to AND $wpdb->p2p.p2p_from IN (%s)) OR
					($main_id_column = $wpdb->p2p.p2p_from AND $wpdb->p2p.p2p_to IN (%s))
				)",
					$this->do_other_query( $directed, 'current' ),
					$this->do_other_query( $directed, 'opposite' )
				);
			}

			$where_parts[] = '(' . $part . ')';
		}

		if ( 1 == count( $where_parts ) )
			$clauses['where'] .= " AND " . $where_parts[0];
		elseif ( !empty( $where_parts ) )
			$clauses['where'] .= " AND (" . implode( ' OR ', $where_parts ) . ")";

		// Handle custom fields
		if ( !empty( $this->meta ) ) {
			$meta_clauses = _p2p_meta_sql_helper( $this->meta );
			foreach ( $meta_clauses as $key => $value ) {
				$clauses[ $key ] .= $value;
			}
		}

		// Handle ordering
		if ( $this->orderby ) {
			$clauses['join'] .= $wpdb->prepare( "
				LEFT JOIN $wpdb->p2pmeta AS p2pm_order ON (
					$wpdb->p2p.p2p_id = p2pm_order.p2p_id AND p2pm_order.meta_key = %s
				)
			", $this->orderby );

			$order = ( 'DESC' == strtoupper( $this->order ) ) ? 'DESC' : 'ASC';

			$field = 'meta_value';

			if ( $this->order_num )
				$field .= '+0';

			$clauses['orderby'] = "p2pm_order.$field $order, " . str_replace( 'ORDER BY ', '', $clauses['orderby'] );
		}

		return $clauses;
	}
}

