<?php

class P2P_Dropdown_Post extends P2P_Dropdown {

	function __construct( $directed, $title ) {
		parent::__construct( $directed, $title );

		add_filter( 'request', array( __CLASS__, 'massage_query' ) );

		add_action( 'restrict_manage_posts', array( $this, 'show_dropdown' ) );
	}

	static function massage_query( $request ) {
		return array_merge( $request, self::get_qv() );
	}
}

