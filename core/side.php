<?php

abstract class P2P_Side {

	public $query_vars;

	function __construct( $query_vars ) {
		$this->query_vars = $query_vars;
	}

	function get_base_qv() {
		return $this->query_vars;
	}
}


class P2P_Side_Post extends P2P_Side {

	public $post_type = array();

	function __construct( $query_vars ) {
		parent::__construct( $query_vars );

		$this->post_type = $this->query_vars['post_type'];
	}

	function get_base_qv() {
		return array_merge( $this->query_vars, array(
			'post_type' => $this->post_type,
			'suppress_filters' => false,
			'ignore_sticky_posts' => true,
		) );
	}

	function get_desc() {
		return implode( ', ', array_map( array( $this, 'post_type_label' ), $this->post_type ) );
	}

	private function post_type_label( $post_type ) {
		$cpt = get_post_type_object( $post_type );
		return $cpt ? $cpt->label : $post_type;
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

	function do_query( $args ) {
		return new WP_Query( $args );
	}

	function abstract_query( $query ) {
		return (object) array(
			'items' => $query->posts,
			'current_page' => max( 1, $query->get('paged') ),
			'total_pages' => $query->max_num_pages
		);
	}

	function translate_qv( $qv ) {
		$map = array(
			'exclude' => 'post__not_in',
			'search' => 's',
			'page' => 'paged',
			'per_page' => 'posts_per_page'
		);

		foreach ( $map as $old => $new )
			if ( isset( $qv["p2p:$old"] ) )
				$qv[$new] = _p2p_pluck( $qv, "p2p:$old" );

		return $qv;
	}

	function item_recognize( $arg ) {
		if ( is_object( $arg ) ) {
			if ( !isset( $arg->post_type ) )
				return false;
			$post_type = $arg->post_type;
		} elseif ( $post_id = (int) $arg ) {
			$post_type = get_post_type( $post_id );
		} else {
			$post_type = $arg;
		}

		if ( !post_type_exists( $post_type ) )
			return false;

		return in_array( $post_type, $this->post_type );
	}

	protected function get_ptype() {
		return get_post_type_object( $this->post_type[0] );
	}

	function item_exists( $item_id ) {
		return (bool) get_post( $item_id );
	}

	function item_title( $item ) {
		return $item->post_title;
	}
}


class P2P_Side_Attachment extends P2P_Side_Post {

	function __construct( $query_vars ) {
		P2P_Side::__construct( $query_vars );

		$this->post_type = array( 'attachment' );
	}
}


class P2P_Side_User extends P2P_Side {

	function get_desc() {
		return __( 'Users', P2P_TEXTDOMAIN );
	}

	function get_title() {
		return $this->get_desc();
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

	function do_query( $args ) {
		return new WP_User_Query( $args );
	}

	function abstract_query( $query ) {
		$qv = $query->query_vars;

		$r = array(
			'items' => $query->get_results()
		);

		if ( isset( $qv['p2p:page'] ) ) {
			$r['current_page'] = $qv['p2p:page'];
			$r['total_pages'] = ceil( $query->get_total() / $qv['p2p:per_page'] );
		} else {
			$r['current_page'] = 1;
			$r['total_pages'] = 0;
		}

		return (object) $r;
	}

	function translate_qv( $qv ) {
		if ( isset( $qv['p2p:exclude'] ) )
			$qv['exclude'] = _p2p_pluck( $qv, 'p2p:exclude' );

		if ( isset( $qv['p2p:search'] ) && $qv['p2p:search'] )
			$qv['search'] = '*' . _p2p_pluck( $qv, 'p2p:search' ) . '*';

		if ( isset( $qv['p2p:page'] ) && $qv['p2p:page'] > 0 ) {
			$qv['number'] = $qv['p2p:per_page'];
			$qv['offset'] = $qv['p2p:per_page'] * ( $qv['p2p:page'] - 1 );
		}

		return $qv;
	}

	function item_recognize( $arg ) {
		return is_a( $arg, 'WP_User' );
	}

	function item_exists( $item_id ) {
		return (bool) get_user_by( 'id', $item_id );
	}

	function item_title( $item ) {
		return $item->display_name;
	}
}

