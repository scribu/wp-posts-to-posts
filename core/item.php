<?php

abstract class P2P_Item {

	protected $item;

	function __construct( $item ) {
		$this->item = $item;
	}

	function get_object() {
		return $this->item;
	}

	function get_id() {
		return $this->item->ID;
	}

	abstract function get_title();
}


class P2P_Item_Post extends P2P_Item {

	function get_title() {
		return get_the_title( $this->item );
	}
}


class P2P_Item_User extends P2P_Item {

	function get_title() {
		return $item->display_name;
	}
}

