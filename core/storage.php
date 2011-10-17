<?php

class P2P_Storage {

	private static $version = 3;

	function init() {
		$table = new scbTable( 'p2p', false, "
			p2p_id bigint(20) unsigned NOT NULL auto_increment,
			p2p_from bigint(20) unsigned NOT NULL,
			p2p_to bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (p2p_id),
			KEY p2p_from (p2p_from),
			KEY p2p_to (p2p_to)
		" );

		$table2 = new scbTable( 'p2pmeta', false, "
			meta_id bigint(20) unsigned NOT NULL auto_increment,
			p2p_id bigint(20) unsigned NOT NULL default '0',
			meta_key varchar(255) default NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY p2p_id (p2p_id),
			KEY meta_key (meta_key)
		" );

		if ( is_admin() && self::$version != get_option( 'p2p_storage' ) ) {
			$table->install();
			$table2->install();

			update_option( 'p2p_storage', self::$version );
		}

		add_action( 'delete_post', array( __CLASS__, 'delete_post' ) );
	}

	function delete_post( $post_id ) {
		self::disconnect( $post_id, 'from' );
		self::disconnect( $post_id, 'to' );
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

P2P_Storage::init();


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

	return get_meta_sql( $meta_query, 'p2p', $wpdb->p2p, 'p2p_id' );
}

