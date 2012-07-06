<?php

/**
 * Handles various db-related tasks
 */
class P2P_Storage {

	static $version = 4;

	static function init() {
		scb_register_table( 'p2p' );
		scb_register_table( 'p2pmeta' );

		add_action( 'deleted_post', array( __CLASS__, 'deleted_object' ) );
		add_action( 'deleted_user', array( __CLASS__, 'deleted_object' ) );
	}

	static function install() {
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

	static function uninstall() {
		scb_uninstall_table( 'p2p' );
		scb_uninstall_table( 'p2pmeta' );

		delete_option( 'p2p_storage' );
	}

	static function deleted_object( $object_id ) {
		$object_type = str_replace( 'deleted_', '', current_filter() );

		foreach ( P2P_Connection_Type_Factory::get_all_instances() as $p2p_type => $ctype ) {
			foreach ( array( 'from', 'to' ) as $direction ) {
				if ( $object_type == $ctype->object[ $direction ] ) {
					p2p_delete_connections( $p2p_type, array(
						$direction => $object_id,
					) );
				}
			}
		}
	}
}

P2P_Storage::init();

