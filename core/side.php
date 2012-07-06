<?php

interface P2P_Side {
	public function get_title();
	public function get_desc();
	public function get_labels();

	public function can_edit_connections();
	public function can_create_item();

	public function get_base_qv( $q );
	public function translate_qv( $qv );
	public function do_query( $args );
	public function capture_query( $args );

	public function is_indeterminate( $side );

	// TODO: break into separate interface
	public function item_recognize( $arg );
	public function item_object( $item_id );
	public function item_id( $arg );
	public function item_title( $item );
}


class P2P_Side_Post implements P2P_Side {

	function __construct( $query_vars ) {
		$this->query_vars = $query_vars;
	}

	public function first_post_type() {
		return $this->query_vars['post_type'][0];
	}

	private function get_ptype() {
		return get_post_type_object( $this->first_post_type() );
	}

	function get_base_qv( $q ) {
		if ( isset( $q['post_type'] ) && 'any' != $q['post_type'] ) {
			$common = array_intersect( $this->query_vars['post_type'], (array) $q['post_type'] );

			if ( !$common )
				unset( $q['post_type'] );
		}

		return array_merge( $this->query_vars, $q, array(
			'suppress_filters' => false,
			'ignore_sticky_posts' => true,
		) );
	}

	function get_desc() {
		return implode( ', ', array_map( array( $this, 'post_type_label' ), $this->query_vars['post_type'] ) );
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

	function can_edit_connections() {
		return current_user_can( $this->get_ptype()->cap->edit_posts );
	}

	function can_create_item() {
		if ( count( $this->query_vars['post_type'] ) > 1 )
			return false;

		if ( count( $this->query_vars ) > 1 )
			return false;

		return true;
	}

	function do_query( $args ) {
		return new WP_Query( $args );
	}

	function capture_query( $args ) {
		$q = new WP_Query;
		$q->_p2p_capture = true;

		$q->query( $args );

		return $q->_p2p_sql;
	}

	function translate_qv( $qv ) {
		$map = array(
			'include' => 'post__in',
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

	function is_indeterminate( $side ) {
		$common = array_intersect(
			$this->query_vars['post_type'],
			$side->query_vars['post_type']
		);

		return !empty( $common );
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

		return in_array( $post_type, $this->query_vars['post_type'] );
	}

	function item_object( $item_id ) {
		return get_post( $item_id );
	}

	function item_id( $arg ) {
		$post = get_post( $arg );
		if ( $post )
			return $post->ID;

		return false;
	}

	function item_title( $item ) {
		return $item->post_title;
	}
}


class P2P_Side_Attachment extends P2P_Side_Post {

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


class P2P_Side_User implements P2P_Side {

	function __construct( $query_vars ) {
		$this->query_vars = $query_vars;
	}

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

	function can_edit_connections() {
		return current_user_can( 'list_users' );
	}

	function can_create_item() {
		return false;
	}

	function do_query( $args ) {
		return new WP_User_Query( $args );
	}

	function capture_query( $args ) {
		$args['count_total'] = false;

		$uq = new WP_User_Query;

		// see http://core.trac.wordpress.org/ticket/21119
		$uq->query_vars = wp_parse_args( $args, array(
			'blog_id' => $GLOBALS['blog_id'],
			'role' => '',
			'meta_key' => '',
			'meta_value' => '',
			'meta_compare' => '',
			'include' => array(),
			'exclude' => array(),
			'search' => '',
			'search_columns' => array(),
			'orderby' => 'login',
			'order' => 'ASC',
			'offset' => '',
			'number' => '',
			'count_total' => true,
			'fields' => 'all',
			'who' => ''
		) );

		$uq->prepare_query();

		return "SELECT $uq->query_fields $uq->query_from $uq->query_where $uq->query_orderby $uq->query_limit";
	}

	function translate_qv( $qv ) {
		if ( isset( $qv['p2p:include'] ) )
			$qv['include'] = _p2p_pluck( $qv, 'p2p:include' );

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

	function is_indeterminate( $side ) {
		return true;
	}

	function item_recognize( $arg ) {
		return is_a( $arg, 'WP_User' );
	}

	function item_object( $item_id ) {
		return get_user_by( 'id', $item_id );
	}

	function item_id( $arg ) {
		if ( $this->item_recognize( $arg ) )
			return $arg->ID;

		$user = get_user_by( 'id', $arg );
		if ( $user )
			return $user->ID;

		return false;
	}

	function item_title( $item ) {
		return $item->display_name;
	}

	function get_base_qv( $q ) {
		return array_merge( $this->query_vars, $q );
	}
}

