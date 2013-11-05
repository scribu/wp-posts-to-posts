<?php

/**
 * A dropdown above a list table in wp-admin
 */
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

	protected function render_dropdown() {
		$direction = $this->ctype->flip_direction()->get_direction();

		$labels = $this->ctype->get( 'current', 'labels' );

		if ( isset( $labels->dropdown_title ) )
			$title = $labels->dropdown_title;
		elseif ( isset( $labels->column_title ) )
			$title = $labels->column_title;
		else
			$title = $this->title;

		return scbForms::input( array(
			'type' => 'select',
			'name' => array( 'p2p', $this->ctype->name, $direction ),
			'choices' => self::get_choices( $this->ctype ),
			'text' => $title,
		), $_GET );
	}

	protected static function get_qv() {
		if ( !isset( $_GET['p2p'] ) )
			return array();

		$args = array();

		$tmp = reset( $_GET['p2p'] );

		$args['connected_type'] = key( $_GET['p2p'] );

		list( $args['connected_direction'], $args['connected_items'] ) = each( $tmp );

		if ( !$args['connected_items'] )
			return array();

		return $args;
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

