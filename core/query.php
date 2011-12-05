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

	protected function expand_connected_type( &$q, $object_type ) {
		$directed = self::find_direction(  _p2p_pluck( $q, 'connected_type' ), $q, $object_type );
		if ( !$directed )
			return false;

		$q = $directed->get_connected_args( $q );

		return true;
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
		$first_directed = self::find_direction( array_shift( $chain ), $q, false );
		if ( !$first_directed )
			return false;

		$directed = $first_directed;

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

			if ( 'any' == $directed->get_direction() ) {
				trigger_error( sprintf( "Ambiguous direction for '%s'.",
					$p2p_type
				), E_USER_NOTICE );
				return false;
			}

			$directions[] = array( $directed->name, $directed->get_direction() );
		}

		if ( $object_type && $directed->get_opposite( 'object' ) != $object_type ) {
			trigger_error( sprintf( "Final object type '%s' does not match expected object type '%s'.",
				$directed->get_opposite( 'object' ),
				$object_type
			), E_USER_NOTICE );
			return false;
		}

		$q = $first_directed->get_connected_args( $q );

		$q['p2p_directions'] = $directions;

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
		if ( !isset( $q['connected_items'] ) )
			return false;

		$qv_list = array(
			'items', 'meta',
			'orderby', 'order_num', 'order'
		);

		foreach ( $qv_list as $key ) {
			$qv[$key] = isset( $q["connected_$key"] ) ? $q["connected_$key"] : false;
		}

		$qv['directions'] = $q['p2p_directions'];

		return $qv;
	}

	function alter_clauses( $clauses, $q, $main_id_column ) {
		global $wpdb;

		// Handle SELECT
		$clauses['fields'] .= ", $wpdb->p2p.*";

		// Handle JOIN
		$i = 0;
		$prev_column = $main_id_column;

		// TODO: join wp_posts with last p2p instance instead of first:

		/*
			SELECT *
			FROM wp_posts
			INNER JOIN (
					SELECT p2p2.p2p_from
					FROM wp_p2p
					INNER JOIN wp_p2p AS p2p2 ON (
						p2p2.p2p_type = 'actor_movie'
						AND wp_p2p.p2p_from IN ( 623 )
						AND wp_p2p.p2p_to = p2p2.p2p_to
						AND wp_p2p.p2p_id <> p2p2.p2p_id
					)
					WHERE 1 =1
					AND wp_p2p.p2p_type = 'actor_movie'
					LIMIT 0 , 30
			) as tmp ON (wp_posts.ID = tmp.p2p_from)

			LIMIT 0 , 30
		 */

		foreach ( $q['directions'] as $dir ) {
			list( $p2p_type, $direction ) = $dir;

			if ( 0 == $i ) {
				$alias = $wpdb->p2p;
				$clauses['join'] .= "\n INNER JOIN $wpdb->p2p ON (";
			} else {
				$alias = 'p2p' . ( $i + 1 );
				$clauses['join'] .= "\n INNER JOIN $wpdb->p2p AS $alias ON (";
			}

			if ( $p2p_type )
				$clauses['join'] .= $wpdb->prepare( "$alias.p2p_type = %s AND ", $p2p_type );

			$fields = array( 'p2p_from', 'p2p_to' );

			switch ( $direction ) {

			case 'from':
				$fields = array_reverse( $fields );
				// fallthrough
			case 'to':
				list( $from, $to ) = $fields;

				if ( $i && $prev_direction != $direction )
					$from = $to;

				$clauses['join'] .= "$prev_column = $alias.$from";

				if ( $i ) {
					$clauses['join'] .= " AND $prev_alias.p2p_id <> $alias.p2p_id";
				}
				break;
			default:
				$clauses['join'] .= "($prev_column = $alias.p2p_to OR $prev_column = $alias.p2p_from)";
			}

			$clauses['join'] .= ")";

			// chain can't contain direction 'any'
			if ( 'any' != $direction ) {
				$prev_alias = $alias;
				$prev_column = "$alias.$from";
				$prev_direction = $direction;
			}

			$i++;
		}

		list( $p2p_type, $direction ) = reset( $q['directions'] );

		// Handle WHERE
		if ( 'any' != $q['items'] ) {
			$search = implode( ',', array_map( 'absint', (array) $q['items'] ) );

			$fields = array( 'p2p_from', 'p2p_to' );

			switch ( $direction ) {

			case 'from':
				$fields = array_reverse( $fields );
				// fallthrough
			case 'to':
				list( $from, $to ) = $fields;

				$clauses['where'] .= " AND $wpdb->p2p.$to IN ($search)";

				break;
			default:
				$clauses['where'] .= " AND (
					($main_id_column = $wpdb->p2p.p2p_to AND $wpdb->p2p.p2p_from IN ($search)) OR
					($main_id_column = $wpdb->p2p.p2p_from AND $wpdb->p2p.p2p_to IN ($search))
				)";
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

