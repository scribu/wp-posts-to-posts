<?php

class P2P_Column_Mock extends P2P_Column {

	function get_items() {
		return array();
	}

	function get_admin_link( $item ) {
		return '';
	}

	function prepare_items() {
		// avoids skewing coverage report
	}
}

