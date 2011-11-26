<?php

define( 'ADMIN_BOX_PER_PAGE', 5 );

abstract class P2P_Side {

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

	private function get_base_qv() {
		return array_merge( $this->query_vars, array(
			'post_type' => $this->post_type,
			'suppress_filters' => false,
			'ignore_sticky_posts' => true,
		) );
	}

	public function get_connected( $directed, $post_id, $extra_qv = array() ) {
		$query = new WP_Query( $this->get_connected_args( $directed, $post_id, $extra_qv ) );
		return scb_list_fold( $query->posts, 'p2p_id', 'ID' );
	}

	public function get_connected_args( $directed, $post_id, $extra_qv = array() ) {
		$args = array_merge( $extra_qv, $this->get_base_qv() );

		// don't completely overwrite 'connected_meta', but ensure that $this->data is added
		$args = array_merge_recursive( $args, array(
			'p2p_type' => $directed->name,
			'connected_posts' => $post_id,
			'connected_direction' => $directed->get_direction(),
			'connected_meta' => $directed->data
		) );

		return apply_filters( 'p2p_connected_args', $args, $directed, $post_id );
	}

	public function get_connectable( $directed, $post_id, $page = 1, $search = '' ) {
		$extra_qv = array(
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'post_status' => 'any',
			'posts_per_page' => ADMIN_BOX_PER_PAGE,
			'paged' => $page,
		);

		if ( $search ) {
			$args['_p2p_box'] = true;
			$args['s'] = $search;
		}

		$args = array_merge( $extra_qv, $this->get_base_qv() );

		if ( $to_check = $directed->cardinality_check( $post_id ) ) {
			$connected = $this->get_connected( $directed, $to_check, array( 'fields' => 'ids' ) );

			if ( !empty( $connected ) ) {
				$args = array_merge_recursive( $args, array(
					'post__not_in' => $connected
				) );
			}
		}

		$args = apply_filters( 'p2p_connectable_args', $args, $directed, $post_id );

		$query = new WP_Query( $args );

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

	public function get_connected( $directed, $post_id, $extra_qv = array() ) {
		$direction = $directed->get_direction();

		$connections = p2p_get_connections( $directed->name, array(
			$direction => $post_id
		) );

		$key = ( 'to' == $direction ) ? 'p2p_from' : 'p2p_to';

		return scb_list_fold( $connections, 'p2p_id', $key );
	}

	public function get_connectable( $directed, $user_id, $page = 1, $search = '' ) {
		$args = array(
			'number' => ADMIN_BOX_PER_PAGE,
			'offset' => ADMIN_BOX_PER_PAGE * ( $page - 1 )
		);

		if ( $search ) {
			$args['search'] = '*' . $search . '*';
		}

		$query = new WP_User_Query( $args );

		return (object) array(
			'items' => $query->get_results(),
			'current_page' => $page,
			'total_pages' => ceil( $query->get_total() / ADMIN_BOX_PER_PAGE )
		);
	}
}

