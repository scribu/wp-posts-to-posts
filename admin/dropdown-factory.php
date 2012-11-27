<?php

class P2P_Dropdown_Factory extends P2P_Factory {

	protected $key = 'admin_dropdown';

	function __construct() {
		parent::__construct();

		add_action( 'restrict_manage_posts', array( $this, 'add_items' ) );
		add_action( 'restrict_manage_users', array( $this, 'add_items' ) );

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
		$method = 'render_dropdown_' . $object_type;

		echo call_user_func( array( __CLASS__, $method ), $directed, $title );
	}

	private static function get_choices( $directed ) {
		$extra_qv = array(
			'p2p:per_page' => -1,
			'p2p:context' => 'admin_dropdown'
		);

		$connected = $directed->get_connected( 'any', $extra_qv, 'abstract' );

		$options = array();
		foreach ( $connected->items as $item )
			$options[ $item->get_id() ] = $item->get_title();

		return $options;
	}

	function render_dropdown_post( $directed, $title ) {
		$direction = $directed->flip_direction()->get_direction();

		return scbForms::input( array(
			'type' => 'select',
			'name' => array( 'p2p', $directed->name, $direction ),
			'choices' => self::get_choices( $directed ),
			'text' => $title,
		), $_GET );
	}

	function render_dropdown_user( $directed, $title ) {
		return html( 'div', array(
			'style' => 'float: right; margin-left: 16px'
		),
			self::render_dropdown_post( $directed, $title ),
			html( 'input', array(
				'type' => 'submit',
				'class' => 'button',
				'value' => __( 'Filter', P2P_TEXTDOMAIN )
			) )
		);
	}
}

new P2P_Dropdown_Factory;

