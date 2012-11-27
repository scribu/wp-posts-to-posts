<?php

abstract class P2P_Dropdown {

	protected $ctype;
	protected $title;

	function __construct( $directed, $title ) {
		$this->ctype = $directed;
		$this->title = $title;
	}

	function show_dropdown() {
		echo $this->render_dropdown();
	}

	function render_dropdown() {
		$direction = $this->ctype->flip_direction()->get_direction();

		return scbForms::input( array(
			'type' => 'select',
			'name' => array( 'p2p', $this->ctype->name, $direction ),
			'choices' => self::get_choices( $this->ctype ),
			'text' => $this->title,
		), $_GET );
	}

	protected static function get_choices( $directed ) {
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
}


class P2P_Dropdown_Post extends P2P_Dropdown {

	function __construct( $directed, $title ) {
		parent::__construct( $directed, $title );

		add_filter( 'request', array( __CLASS__, 'massage_query' ) );

		add_action( 'restrict_manage_posts', array( $this, 'show_dropdown' ) );
	}

	static function massage_query( $request ) {
		if ( isset( $_GET['p2p'] ) ) {
			$args = array();

			$tmp = reset( $_GET['p2p'] );

			$args['connected_type'] = key( $_GET['p2p'] );

			list( $args['connected_direction'], $args['connected_items'] ) = each( $tmp );

			if ( $args['connected_items'] ) {
				_p2p_append( $request, $args );
			}
		}

		return $request;
	}
}


class P2P_Dropdown_User extends P2P_Dropdown_Post {

	function __construct( $directed, $title ) {
		parent::__construct( $directed, $title );

		add_action( 'restrict_manage_users', array( $this, 'show_dropdown' ) );
	}

	function render_dropdown() {
		return html( 'div', array(
			'style' => 'float: right; margin-left: 16px'
		),
			parent::render_dropdown(),
			html( 'input', array(
				'type' => 'submit',
				'class' => 'button',
				'value' => __( 'Filter', P2P_TEXTDOMAIN )
			) )
		);
	}
}

