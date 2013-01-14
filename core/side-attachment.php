<?php

class P2P_Side_Attachment extends P2P_Side_Post {

	protected $item_type = 'P2P_Item_Attachment';

	function __construct( $query_vars ) {
		$this->query_vars = $query_vars;

		$this->query_vars['post_type'] = array( 'attachment' );
	}

	function can_create_item() {
		return false;
	}

	function get_base_qv( $q ) {
		return array_merge( parent::get_base_qv( $q ), array(
			'post_status' => 'inherit'
		) );
	}
}

