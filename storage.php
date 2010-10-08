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
	function get( $from, $to, $data = array(), $_return_p2p_ids = false ) {
		global $wpdb;

		$select = "p2p_id";
		$where = "";

		switch ( $to ) {
			case 'from':
				$select .= $_return_p2p_ids ? '' : ', p2p_to AS post_id';
				$where .= $wpdb->prepare( "p2p_from = %d", $from );
				break;
			case 'to':
				$select .= $_return_p2p_ids ? '' : ', p2p_from AS post_id';
				$where .= $wpdb->prepare( "p2p_to = %d", $from );
				break;
			default:
				$where .= $wpdb->prepare( "p2p_from = %d AND p2p_to = %d", $from, $to );
				$_return_p2p_ids = true;
		}

		if ( !empty( $data ) ) {
			$clauses = array();
			foreach ( $data as $key => $value ) {
				$clauses[] = $wpdb->prepare( "WHEN %s THEN meta_value = %s ", $key, $value );
			}

			$where .= " AND p2p_id IN (
				SELECT p2p_id
				FROM $wpdb->p2pmeta
				WHERE CASE meta_key 
				" . implode( "\n", $clauses ) . "
				END
				GROUP BY p2p_id HAVING COUNT(p2p_id) = " . count($data) . "
			)";
		}

		$query = "SELECT $select FROM $wpdb->p2p WHERE $where";

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
	 *
	 * @return int|bool connection id or False on failure
	 */
	function connect( $from, $to, $data = array() ) {
		global $wpdb;

		$from = absint( $from );
		$to = absint( $to );

		if ( !$from || !$to )
			return false;

		$p2p_ids = self::get( $from, $to, $data, true );

		if ( !empty( $p2p_ids ) )
			return $p2p_ids[0];

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
		return self::delete( self::get( $from, $to, $data, true ) );
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

