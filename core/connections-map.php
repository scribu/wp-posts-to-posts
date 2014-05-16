<?php

class P2P_Connections_Map {

	private $directed;
	private $buckets;

	function __construct( $items, $directed ) {
		$this->buckets = scb_list_group_by( $items, '_p2p_get_other_id' );
		$this->directed = $directed;
	}

	function _for( $item ) {
		return $this->buckets[ $item->ID ];
	}
}

