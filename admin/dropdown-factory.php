<?php

class P2P_Dropdown_Factory extends P2P_Factory {

	protected $key = 'admin_dropdown';

	function __construct() {
		parent::__construct();

		add_action( 'restrict_manage_posts', array( $this, 'add_items' ) );
	}

	function add_item( $directed, $object_type, $post_type, $title ) {
		$extra_qv = array(
			'p2p:per_page' => -1,
			'p2p:context' => 'admin_dropdown'
		);

		$connected = $directed->get_connected( 'any', $extra_qv, 'abstract' );

		$options = array();
		foreach ( $connected->items as $item )
			$options[ $item->get_id() ] = $item->get_title();

		echo scbForms::input( array(
			'type' => 'select',
			'name' => 'connected_items',
			'choices' => $options,
			'selected' => false, // TODO
			'text' => ''// TODO
		) );
	}
}

new P2P_Dropdown_Factory;

