<?php

abstract class P2P_Side {

	function __construct( $args ) {
		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}
	}

	abstract function get_title();
}


class P2P_Side_Post extends P2P_Side {

	function get_base_qv() {
		return array_merge( $this->query_vars, array(
			'post_type' => $this->post_type,
			'suppress_filters' => false,
			'ignore_sticky_posts' => true,
		) );
	}

	function get_title() {
		return get_post_type_object( $this->post_type[0] )->labels->name;
	}
}


class P2P_Side_User extends P2P_Side {

	function get_title() {
		return __( 'Users', P2P_TEXTDOMAIN );
	}
}

