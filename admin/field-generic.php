<?php

class P2P_Field_Generic implements P2P_Field {

	protected $key;
	protected $data;

	function __construct( $key, $data ) {
		$this->key = $key;
		$this->data = $data;
	}

	function get_title() {
		return $this->data['title'];
	}

	function render( $p2p_id, $_ ) {
		$args = $this->data;
		$args['name'] = array( 'p2p_meta', $p2p_id, $this->key );

		if ( 'select' == $args['type'] && !isset( $args['text'] ) )
			$args['text'] = '';

		return scbForms::input_from_meta( $args, $p2p_id, 'p2p' );
	}
}

