<?php

class P2P_Field_Order implements P2P_Field {

	protected $sort_key;

	function __construct( $sort_key ) {
		$this->sort_key = $sort_key;
	}

	function get_title() {
		return '';
	}

	function render( $p2p_id, $_ ) {
		return html( 'input', array(
			'type' => 'hidden',
			'name' => "p2p_order[$this->sort_key][]",
			'value' => $p2p_id
		) );
	}
}

