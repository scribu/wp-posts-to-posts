<?php

/**
 * Handles various db-related tasks
 */
class P2P_Storage {

	static $version = 4;

	function init() {
		scb_register_table( 'p2p' );
		scb_register_table( 'p2pmeta' );

		add_action( 'deleted_post', array( __CLASS__, 'deleted_post' ) );
	}

	function install() {
		scb_install_table( 'p2p', "
			p2p_id bigint(20) unsigned NOT NULL auto_increment,
			p2p_from bigint(20) unsigned NOT NULL,
			p2p_to bigint(20) unsigned NOT NULL,
			p2p_type varchar(44) NOT NULL default '',
			PRIMARY KEY  (p2p_id),
			KEY p2p_from (p2p_from),
			KEY p2p_to (p2p_to),
			KEY p2p_type (p2p_type)
		" );

		scb_install_table( 'p2pmeta', "
			meta_id bigint(20) unsigned NOT NULL auto_increment,
			p2p_id bigint(20) unsigned NOT NULL default '0',
			meta_key varchar(255) default NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY p2p_id (p2p_id),
			KEY meta_key (meta_key)
		" );
	}

	function upgrade() {
		global $wpdb;

		$n = 0;

		foreach ( P2P_Connection_Type_Factory::get_all_instances() as $p2p_type => $ctype ) {
			if ( ! $ctype instanceof P2P_Connection_Type )
				continue;

			$args = $ctype->set_direction( 'any' )->get_connected_args( array(
				'connected_items' => 'any',
				'cache_results' => false,
				'post_status' => 'any',
				'nopaging' => true
			) );

			$args['p2p_type'] = array( 0 => 'any' );

			foreach ( get_posts( $args ) as $post ) {
				// some connections might be ambiguous, spanning multiple connection types; first one wins
				if ( $post->p2p_type )
					continue;

				$n += $wpdb->update( $wpdb->p2p, compact( 'p2p_type' ), array( 'p2p_id' => $post->p2p_id ) );
			}
		}

		return $n;
	}

	function uninstall() {
		scb_uninstall_table( 'p2p' );
		scb_uninstall_table( 'p2pmeta' );

		delete_option( 'p2p_storage' );
	}

	function deleted_post( $post_id ) {
		foreach ( P2P_Connection_Type_Factory::get_all_instances() as $p2p_type => $ctype ) {
			foreach ( array( 'from', 'to' ) as $direction ) {
				if ( 'post' == $ctype->object[ $direction ] ) {
					p2p_delete_connections( $p2p_type, array(
						$direction => $post_id,
					) );
				}
			}
		}
	}
}

P2P_Storage::init();

