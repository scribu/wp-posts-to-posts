<?php

abstract class P2P_List {
	public $items;
	public $current_page;
	public $total_pages;

	abstract function render( $args = array() );
}


class P2P_List_Post extends P2P_List {

	function __construct( $wp_query ) {
		$this->items = $wp_query->posts;
		$this->current_page = max( 1, $wp_query->get('paged') );
		$this->total_pages = $wp_query->max_num_pages;
	}

	function render( $args = array() ) {
		// TODO
	}
}


class P2P_List_User extends P2P_List {

	function __construct( $query ) {
		$qv = $query->query_vars;

		$this->items = $query->get_results();

		if ( isset( $qv['p2p:page'] ) ) {
			$this->current_page = $qv['p2p:page'];
			$this->total_pages = ceil( $query->get_total() / $qv['p2p:per_page'] );
		} else {
			$this->current_page = 1;
			$this->total_pages = 0;
		}
	}

	function render( $args = array() ) {
		// TODO
	}
}

