<?php

class P2P_Field_Create implements P2P_Field {

	protected $title_field;

	function __construct( $title_field ) {
		$this->title_field = $title_field;
	}

	function get_title() {
		// Not needed
		return '';
	}

	function render( $p2p_id, $item ) {
		$data = array_merge( $this->title_field->get_data( $item ), array(
			'title' => $item->get_title(),
			'item-id' => $item->get_id(),
		) );

		return P2P_Mustache::render( 'column-create', $data );
	}
}

