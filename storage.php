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
		self::delete( $post_id, 'from' );
		self::delete( $post_id, 'to' );
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
	function get( $from, $to, $data = array() ) {
		global $wpdb;

		$select = "";
		$where = "";

		switch ( $to ) {
			case 'from':
				$select .= "p2p_id, p2p_to AS post_id";
				$where .= $wpdb->prepare( "p2p_from = %d", $from );
				break;
			case 'to':
				$select .= "p2p_id, p2p_from AS post_id";
				$where .= $wpdb->prepare( "p2p_to = %d", $from );
				break;
			default:
				$select .= "p2p_id";
				$where .= $wpdb->prepare( "p2p_from = %d AND p2p_to = %d", $from, $to );
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

		if ( is_numeric( $to ) )
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
	function add( $from, $to, $data = array() ) {
		global $wpdb;

		$from = absint($from);
		$to = absint($to);

		if ( !$from || !$to )
			return false;

		$ids = self::get( $from, $to, $data );

		if ( !empty( $ids ) )
			return $ids[0];

		$wpdb->insert( $wpdb->p2p, array( 'p2p_from' => $from, 'p2p_to' => $to ), '%d' );

		$p2p_id = $wpdb->insert_id;

		foreach ( $data as $key => $value )
			add_p2p_meta( $p2p_id, $key, $value );

		return $p2p_id;
	}

	/**
	 * Disconnect two posts
	 *
	 * @param int $from post id
	 * @param int|string $to post id or direction: 'from' or 'to'
	 * @param array $data additional data about the connection
	 *
	 * @return int Number of connections deleted
	 */
	function delete( $from, $to, $data = array() ) {
		global $wpdb;

		$ids = self::get( $from, $to, $data );

		if ( empty( $ids ) )
			return 0;

		$where = "WHERE p2p_id IN (" . implode(',', $ids ) . ")";

		$wpdb->query( "DELETE FROM $wpdb->p2p $where" );
		$wpdb->query( "DELETE FROM $wpdb->p2pmeta $where" );

		return count( $ids );
	}
}


function get_p2p_meta($p2p_id, $key, $single = false) {
	return get_metadata('p2p', $p2p_id, $key, $single);
}

function update_p2p_meta($p2p_id, $meta_key, $meta_value, $prev_value = '') {
	return update_metadata('p2p', $p2p_id, $meta_key, $meta_value, $prev_value);
}

function add_p2p_meta($p2p_id, $meta_key, $meta_value, $unique = false) {
	return add_metadata('p2p', $p2p_id, $meta_key, $meta_value, $unique);
}

function delete_p2p_meta($p2p_id, $meta_key, $meta_value = '') {
	return delete_metadata('p2p', $p2p_id, $meta_key, $meta_value);
}

