<?php

class P2P_Connections {

	function init( $file ) {
		$table = new scbTable( 'p2p', $file, "
			p2p_id bigint(20) unsigned NOT NULL auto_increment,
			p2p_from bigint(20) unsigned NOT NULL,
			p2p_to bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (p2p_id),
			KEY p2p_from (p2p_from),
			KEY p2p_to (p2p_to)
		" );

		$table2 = new scbTable( 'p2pmeta', $file, "
			meta_id bigint(20) unsigned NOT NULL auto_increment,
			p2p_id bigint(20) unsigned NOT NULL default '0',
			meta_key varchar(255) default NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY p2p_id (p2p_id),
			KEY meta_key (meta_key)
		" );

// FORCE UPDATE
#add_action('init', array($table, 'install'));
#add_action('init', array($table2, 'install'));

		add_action( 'delete_post', array( __CLASS__, 'delete_post' ) );
	}

	function delete_post( $post_id ) {
		self::disconnect( $post_id, 'from' );
		self::disconnect( $post_id, 'to' );
	}

	/**
	 * Get a list of connections, given a certain post id
	 *
	 * @param int $from post id
	 * @param int|string $to post id or direction: 'from' or 'to'
	 * @param array $data additional data about the connection to filter against
	 *
	 * @return array( p2p_id => post_id ) if $to is string
	 * @return array( p2p_id ) if $to is int
	 */
	function get( $post_id, $direction, $data = array() ) {
		if ( 'any' == $direction ) {
			$from = self::_get( $post_id, 'from', $data );
			$to = self::_get( $post_id, 'to', $data );

			foreach ( $to as $p2p_id => $post_id ) {
				$from[ $p2p_id ] = $post_id;
			}

			return $from;
		}

		return self::_get( $post_id, $direction, $data );
	}

	private function _get( $from, $to, $data = array() ) {
		global $wpdb;

		$fields = "$wpdb->p2p.p2p_id";
		$where = '';
		$join = '';

		$_return_p2p_ids = false;
		switch ( $to ) {
			case 'from':
				$fields .= ', p2p_to AS post_id';
				$where .= $wpdb->prepare( "p2p_from = %d", $from );
				break;
			case 'to':
				$fields .= ', p2p_from AS post_id';
				$where .= $wpdb->prepare( "p2p_to = %d", $from );
				break;
			default:
				$where .= $wpdb->prepare( "p2p_from = %d AND p2p_to = %d", $from, $to );
				$_return_p2p_ids = true;
		}

		if ( !empty( $data ) ) {
			$clauses = _p2p_meta_sql_helper( $data );
			$join .= $clauses['join'];
			$where .= $clauses['where'];
		}

		$query = "SELECT $fields FROM $wpdb->p2p $join WHERE $where";

		if ( $_return_p2p_ids )
			return $wpdb->get_col( $query );

		$results = $wpdb->get_results( $query );

		$r = array();
		foreach ( $results as $row )
			$r[ $row->p2p_id ] = $row->post_id;

		return $r;
	}

	/**
	 * Connect two posts
	 *
	 * @param int $from post id
	 * @param int $to post id
	 * @param array $data additional data about the connection
	 * @param string $duplicates Duplicate prevention strategy: 'none', 'matching_data', 'strict'
	 *
	 * @return int|bool connection id or False on failure
	 */
	function connect( $from, $to, $data = array(), $duplicates = 'matching_data' ) {
		global $wpdb;

		$from = absint( $from );
		$to = absint( $to );

		if ( !$from || !$to )
			return false;

		$wpdb->insert( $wpdb->p2p, array( 'p2p_from' => $from, 'p2p_to' => $to ), '%d' );

		$p2p_id = $wpdb->insert_id;

		foreach ( $data as $key => $value )
			p2p_add_meta( $p2p_id, $key, $value );

		return $p2p_id;
	}

	/**
	 * Disconnect two posts
	 *
	 * @param int $from post id
	 * @param int|string $to post id or direction: 'from' or 'to'
	 * @param array $data additional data about the connection to filter against
	 *
	 * @return int Number of connections deleted
	 */
	function disconnect( $from, $to, $data = array() ) {
		$connections = self::get( $from, $to, $data );

		// We're interested in the p2p_ids
		if ( !(int) $to )
			$connections = array_keys( $connections );

		return self::delete( $connections );
	}

	/**
	 * Delete one or more connections
	 *
	 * @param int|array $p2p_id Connection ids
	 *
	 * @return int Number of connections deleted
	 */
	function delete( $p2p_id ) {
		global $wpdb;

		if ( empty( $p2p_id ) )
			return 0;

		$p2p_ids = array_map( 'absint', (array) $p2p_id );

		$where = "WHERE p2p_id IN (" . implode( ',', $p2p_ids ) . ")";

		$wpdb->query( "DELETE FROM $wpdb->p2p $where" );
		$wpdb->query( "DELETE FROM $wpdb->p2pmeta $where" );

		return count( $p2p_ids );
	}
}


function p2p_get_meta($p2p_id, $key, $single = false) {
	return get_metadata('p2p', $p2p_id, $key, $single);
}

function p2p_update_meta($p2p_id, $meta_key, $meta_value, $prev_value = '') {
	return update_metadata('p2p', $p2p_id, $meta_key, $meta_value, $prev_value);
}

function p2p_add_meta($p2p_id, $meta_key, $meta_value, $unique = false) {
	return add_metadata('p2p', $p2p_id, $meta_key, $meta_value, $unique);
}

function p2p_delete_meta($p2p_id, $meta_key, $meta_value = '') {
	return delete_metadata('p2p', $p2p_id, $meta_key, $meta_value);
}

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

	return p2p_get_meta_sql( $meta_query, 'p2p', $wpdb->p2p, 'p2p_id' );
}

// WP < 3.1-alpha
function p2p_get_meta_sql( $meta_query, $meta_type, $primary_table, $primary_id_column ) {
	global $wpdb;

	if ( ! $meta_table = _get_meta_table( $meta_type ) )
		return false;

	$meta_id_column = esc_sql( $meta_type . '_id' );

	$clauses = array();

	$join = '';
	$where = '';
	$i = 0;
	foreach ( $meta_query as $q ) {
		$meta_key = isset( $q['key'] ) ? trim( $q['key'] ) : '';
		$meta_value = isset( $q['value'] ) ? $q['value'] : '';
		$meta_compare = isset( $q['compare'] ) ? strtoupper( $q['compare'] ) : '=';
		$meta_type = isset( $q['type'] ) ? strtoupper( $q['type'] ) : 'CHAR';

		if ( ! in_array( $meta_compare, array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) )
			$meta_compare = '=';

		if ( 'NUMERIC' == $meta_type )
			$meta_type = 'SIGNED';
		elseif ( ! in_array( $meta_type, array( 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED' ) ) )
			$meta_type = 'CHAR';

		if ( empty( $meta_key ) && empty( $meta_value ) )
			continue;

		$alias = $i ? 'mt' . $i : $meta_table;

		$join .= "\nINNER JOIN $meta_table";
		$join .= $i ? " AS $alias" : '';
		$join .= " ON ($primary_table.$primary_id_column = $alias.$meta_id_column)";

		$i++;

		if ( !empty( $meta_key ) )
			$where .= $wpdb->prepare( " AND $alias.meta_key = %s", $meta_key );

		if ( in_array( $meta_compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) ) {
			if ( ! is_array( $meta_value ) )
				$meta_value = preg_split( '/[,\s]+/', $meta_value );
		} else {
			$meta_value = trim( $meta_value );
		}

		if ( empty( $meta_value ) )
			continue;

		if ( 'IN' == substr( $meta_compare, -2) ) {
			$meta_field_types = substr( str_repeat( ',%s', count( $meta_value ) ), 1 );
			$meta_compare_string = "($meta_field_types)";
			unset( $meta_field_types );
		} elseif ( 'BETWEEN' == substr( $meta_compare, -7) ) {
			$meta_value = array_slice( $meta_value, 0, 2 );
			$meta_compare_string = '%s AND %s';
		} elseif ( 'LIKE' == substr( $meta_compare, -4 ) ) {
			$meta_value = '%' . like_escape( $meta_value ) . '%';
			$meta_compare_string = '%s';
		} else {
			$meta_compare_string = '%s';
		}
		$where .= $wpdb->prepare( " AND CAST($alias.meta_value AS {$meta_type}) {$meta_compare} {$meta_compare_string}", $meta_value );

		unset( $meta_compare_string );
	}

	return compact( 'join', 'where' );
}

