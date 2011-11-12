<?php

class P2P_Ordered_Connection_Type extends P2P_Directed_Connection_Type {

	public function get_orderby_field() {
		if ( 'any' == $this->sortable || $this->direction == $this->sortable )
			return '_order_' . $this->direction;

		if ( 'from' == $this->direction )
			return $this->sortable;
	}

	public function get_connected( $post_id, $extra_qv = array() ) {
		$extra_qv = wp_parse_args( $extra_qv, array(
			'connected_orderby' => $this->get_orderby_field(),
			'connected_order' => 'ASC',
			'connected_order_num' => true,
		) );

		return parent::get_connected( $post_id, $extra_qv );
	}
}

