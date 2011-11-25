<?php

/**
 * Handles various db-related tasks
 */
class P2P_Storage {

	static $version = 4;

	function init() {
		scb_register_table( 'p2p' );
		scb_register_table( 'p2pmeta' );

		add_action( 'admin_notices', array( __CLASS__, 'install' ) );
		add_action( 'deleted_post', array( __CLASS__, 'deleted_post' ) );
	}

	function install() {
		if ( !current_user_can( 'manage_options' ) )
			return;

		$current_ver = get_option( 'p2p_storage' );

		if ( $current_ver == self::$version )
			return;

		scb_install_table( 'p2p', "
			p2p_id bigint(20) unsigned NOT NULL auto_increment,
			p2p_from bigint(20) unsigned NOT NULL,
			p2p_to bigint(20) unsigned NOT NULL,
			p2p_type varchar(32) NOT NULL default '',
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

		if ( $current_ver ) {
			echo scb_admin_notice( sprintf(
				__( 'You need to run the <a href="%s">upgrade script</a> before using Posts 2 Posts again.', P2P_TEXTDOMAIN ),
				admin_url( 'tools.php?page=p2p-tools&upgrade' )
			) );
		} else {
			update_option( 'p2p_storage', P2P_Storage::$version );
		}
	}

	function deleted_post( $post_id ) {
		foreach ( P2P_Connection_Type_Factory::get_all_instances() as $p2p_type => $ctype ) {
			foreach ( array( 'from', 'to' ) as $direction ) {
				if ( 'post' == $ctype->side[ $direction ]->object ) {
					p2p_delete_connections( $p2p_type, array(
						$direction => $post_id,
					) );
				}
			}
		}
	}
}

P2P_Storage::init();

