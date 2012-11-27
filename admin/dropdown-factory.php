<?php

class P2P_Dropdown_Factory extends P2P_Factory {

	protected $key = 'admin_dropdown';

	function __construct() {
		parent::__construct();

		add_action( 'restrict_manage_posts', array( $this, 'add_items' ) );

		add_filter( 'request', array( $this, 'request' ) );
	}

	function request( $request ) {
		if ( isset( $_GET['p2p'] ) ) {
			$args = array();

			list( $args['connected_type'], $tmp ) = each( $_GET['p2p'] );
			list( $args['connected_direction'], $args['connected_items'] ) = each( $tmp );

			if ( $args['connected_items'] ) {
				_p2p_append( $request, $args );
			}
		}

		return $request;
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

		$direction = $directed->flip_direction()->get_direction();

		echo scbForms::input( array(
			'type' => 'select',
			'name' => array( 'p2p', $directed->name, $direction ),
			'choices' => $options,
			'text' => $directed->get( 'current', 'title' )
		), $_GET );
	}
}

new P2P_Dropdown_Factory;

