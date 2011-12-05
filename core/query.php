<?php

class P2P_Query {

	// null means do nothing
	// false means trigger 404
	// true means found valid p2p query vars
	function handle_qv( &$q, $object_type ) {
		self::expand_shortcut_qv( $q );

		if ( !isset( $q['connected_items'] ) )
			return;

		if ( isset( $q['connected_type'] ) )
			return self::expand_connected_type( $q, $object_type );

		if ( isset( $q['connected_chain'] ) )
			return self::expand_connected_chain( $q, $object_type );
	}

	protected function expand_connected_chain( &$q, $object_type ) {
		$chain = _p2p_pluck( $q, 'connected_chain' );

		foreach ( $chain as $p2p_type ) {
			$ctype = p2p_type( $p2p_type );
			if ( !$ctype ) {
				trigger_error( "Undefined connection type: '$p2p_type'", E_USER_NOTICE );
				return false;
			}
		}

		// the $object_type hint will be used on the final connection type
		$directed = self::find_direction( array_shift( $chain ), $q, false );
		if ( !$directed )
			return false;

		$directions = array(
			array( $directed->name, $directed->get_direction() )
		);

		foreach ( $chain as $p2p_type ) {
			$ctype = p2p_type( $p2p_type );

			$side = $directed->get_opposite( 'side' );

			if ( isset( $side->post_type ) )
				$post_type = $side->post_type[0];
			else
				$post_type = false;

			$directed = $ctype->find_direction( $post_type, true, $directed->get_current( 'object' ) );
			if ( !$directed )
				return false;

			$directions[] = array( $directed->name, $directed->get_direction() );
		}

		if ( $object_type && $directed->get_opposite( 'object' ) != $object_type ) {
			trigger_error( sprintf( "Final object type '%s' does not match expected object type '%s'.",
				$directed->get_opposite( 'object' ),
				$object_type
			), E_USER_NOTICE );
			return false;
		}

		$q['p2p_directions'] = $directions;

		return true;
	}

	protected function expand_connected_type( &$q, $object_type ) {
		$directed = self::find_direction(  _p2p_pluck( $q, 'connected_type' ), $q, $object_type );
		if ( !$directed )
			return false;

		$q = $directed->get_connected_args( $q );

		return true;
	}

	protected function find_direction( $p2p_type, &$q, $object_type ) {
		$ctype = p2p_type( $p2p_type );

		if ( !$ctype )
			return false;

		if ( isset( $q['connected_direction'] ) )
			return $ctype->set_direction( _p2p_pluck( $q, 'connected_direction' ) );
		else {
			return $ctype->find_direction( $q['connected_items'], true, $object_type );
		}
	}

	protected function expand_shortcut_qv( &$q ) {
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

	function get_qv( $q ) {
		$qv_list = array(
			'items', 'direction', 'meta',
			'orderby', 'order_num', 'order'
		);

		foreach ( $qv_list as $key ) {
			$qv[$key] = isset( $q["connected_$key"] ) ?  $q["connected_$key"] : false;
		}

		$qv['p2p_type'] = isset( $q['p2p_type'] ) ?  $q['p2p_type'] : false;

		return $qv;
	}

	function alter_clauses( $clauses, $q, $main_id_column ) {
		global $wpdb;

		$clauses['fields'] .= ", $wpdb->p2p.*";

		$clauses['join'] .= " INNER JOIN $wpdb->p2p";

		// Handle main query
		if ( $q['p2p_type'] )
			$clauses['where'] .= $wpdb->prepare( " AND $wpdb->p2p.p2p_type = %s", $q['p2p_type'] );

		if ( 'any' == $q['items'] ) {
			$search = false;
		} else {
			$search = implode( ',', array_map( 'absint', (array) $q['items'] ) );
		}

		$fields = array( 'p2p_from', 'p2p_to' );

		switch ( $q['direction'] ) {

		case 'from':
			$fields = array_reverse( $fields );
			// fallthrough
		case 'to':
			list( $from, $to ) = $fields;

			$clauses['where'] .= " AND $main_id_column = $wpdb->p2p.$from";
			if ( $search ) {
				$clauses['where'] .= " AND $wpdb->p2p.$to IN ($search)";
			}

			break;
		default:
			if ( $search ) {
				$clauses['where'] .= " AND (
					($main_id_column = $wpdb->p2p.p2p_to AND $wpdb->p2p.p2p_from IN ($search)) OR
					($main_id_column = $wpdb->p2p.p2p_from AND $wpdb->p2p.p2p_to IN ($search))
				)";
			} else {
				$clauses['where'] .= " AND ($main_id_column = $wpdb->p2p.p2p_to OR $main_id_column = $wpdb->p2p.p2p_from)";
			}
		}

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
}

