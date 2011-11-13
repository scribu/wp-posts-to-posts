<?php

class P2P_Ordered_Connection_Type extends P2P_Directed_Connection_Type {

	protected $orderby_key;

	function __construct( $ctype, $direction, $orderby_key ) {
		$this->orderby_key = $orderby_key;

		parent::__construct( $ctype, $direction );
	}

	public function get_orderby_key() {
		return $this->orderby_key;
	}

	public function get_connected( $post_id, $extra_qv = array() ) {
		$extra_qv = wp_parse_args( $extra_qv, array(
			'connected_orderby' => $this->get_orderby_key(),
			'connected_order' => 'ASC',
			'connected_order_num' => true,
		) );

		return parent::get_connected( $post_id, $extra_qv );
	}
}

