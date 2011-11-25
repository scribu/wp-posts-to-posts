<?php

class P2P_Side {

	function __construct( $args ) {
		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}
	}
}


class P2P_Side_Post extends P2P_Side {

	function get_base_qv() {
		return array_merge( $this->query_vars, array(
			'post_type' => $this->post_type,
			'suppress_filters' => false,
			'ignore_sticky_posts' => true,
		) );
	}
}


class P2P_Side_User extends P2P_Side {

}

