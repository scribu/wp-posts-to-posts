<?php

define( 'ADMIN_BOX_PER_PAGE', 5 );

abstract class P2P_Side {

	public $query_vars = array();

	function __construct( $args ) {
		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}
	}

	abstract function get_title();
	abstract function get_labels();

	abstract function check_capability();
}


class P2P_Side_Post extends P2P_Side {

	function __construct( $args ) {
		parent::__construct( $args );

		$this->post_type = array_values( array_filter( $this->post_type, 'post_type_exists' ) );
		if ( empty( $this->post_type ) )
			$this->post_type = array( 'post' );

		$this->query_vars = array_merge( $this->query_vars, array(
			'post_type' => $this->post_type,
			'suppress_filters' => false,
			'ignore_sticky_posts' => true,
		) );
	}

	function get_title() {
		return $this->get_ptype()->labels->name;
	}

	function get_labels() {
		return $this->get_ptype()->labels;
	}

	function check_capability() {
		return current_user_can( $this->get_ptype()->cap->edit_posts );
	}

	public function get_connected( $args ) {
		return new WP_Query( $args );
	}

	private static $admin_box_qv = array(
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'post_status' => 'any',
	);

	public function get_connections( $directed, $item_id ) {
		$qv = array_merge( self::$admin_box_qv, array(
			'nopaging' => true
		) );

		$query = $directed->get_connected( $item_id, $qv );

		return scb_list_fold( $query->posts, 'p2p_id', 'ID' );
	}

	public function get_connectable( $directed, $item_id, $page = 1, $search = '' ) {
		$qv = array_merge( $this->query_vars, self::$admin_box_qv, array(
			'posts_per_page' => ADMIN_BOX_PER_PAGE,
			'paged' => $page,
		) );

		if ( $search ) {
			$qv['_p2p_box'] = true;
			$qv['s'] = $search;
		}

		$to_check = $directed->cardinality_check( $item_id );
		if ( !empty( $to_check ) ) {
			$qv['post__not_in'] = $to_check;
		}

		$qv = apply_filters( 'p2p_connectable_args', $qv, $directed, $item_id );

		$query = new WP_Query( $qv );

		return (object) array(
			'items' => $query->posts,
			'current_page' => max( 1, $query->get('paged') ),
			'total_pages' => $query->max_num_pages
		);
	}

	private function get_ptype() {
		return get_post_type_object( $this->post_type[0] );
	}
}


class P2P_Side_User extends P2P_Side {

	function get_title() {
		return __( 'Users', P2P_TEXTDOMAIN );
	}

	function get_labels() {
		return (object) array(
			'singular_name' => __( 'User', P2P_TEXTDOMAIN ),
			'search_items' => __( 'Search Users', P2P_TEXTDOMAIN ),
			'not_found' => __( 'No users found.', P2P_TEXTDOMAIN ),
		);
	}

	function check_capability() {
		return current_user_can( 'list_users' );
	}

	public function get_connected( $args ) {
		return new WP_User_Query( $args );
	}

	public function get_connections( $directed, $item_id ) {
		$query = $directed->get_connected( $item_id );

		return scb_list_fold( $query->results, 'p2p_id', 'ID' );
	}

	public function get_connectable( $directed, $item_id, $page = 1, $search = '' ) {
		$qv = array(
			'number' => ADMIN_BOX_PER_PAGE,
			'offset' => ADMIN_BOX_PER_PAGE * ( $page - 1 )
		);

		if ( $search ) {
			$qv['search'] = '*' . $search . '*';
		}

		$to_check = $directed->cardinality_check( $item_id );
		if ( !empty( $to_check ) ) {
			$qv['exclude'] = $to_check;
		}

		$qv = apply_filters( 'p2p_connectable_args', $qv, $directed, $item_id );

		$query = new WP_User_Query( $qv );

		return (object) array(
			'items' => $query->get_results(),
			'current_page' => $page,
			'total_pages' => ceil( $query->get_total() / ADMIN_BOX_PER_PAGE )
		);
	}
}

