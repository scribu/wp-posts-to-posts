<?php

abstract class P2P_Field_Title implements P2P_Field {

	protected $title;

	function __construct( $title = '' ) {
		$this->title = $title;
	}

	function get_title() {
		return $this->title;
	}

	function render( $p2p_id, $item ) {
		$data = array_merge( $this->get_data( $item ), array(
			'title' => $item->title,
			'url'   => $item->get_editlink(),
		) );

		return P2P_Mustache::render( 'column-title', $data );
	}

	abstract function get_data( $item );
}

