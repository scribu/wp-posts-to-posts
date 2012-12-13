<?php

abstract class P2P_Side {

	protected $item_type;

	abstract function get_object_type();

	abstract function get_title();
	abstract function get_desc();
	abstract function get_labels();

	abstract function can_edit_connections();
	abstract function can_create_item();

	abstract function get_base_qv( $q );
	abstract function translate_qv( $qv );
	abstract function do_query( $args );
	abstract function capture_query( $args );
	abstract function get_list( $query );

	abstract function is_indeterminate( $side );

	final function is_same_type( $side ) {
		return $this->get_object_type() == $side->get_object_type();
	}

	/**
	 * @param object Raw object or P2P_Item
	 */
	function item_recognize( $arg ) {
		$class = $this->item_type;

		if ( is_a( $arg, 'P2P_Item' ) ) {
			if ( !is_a( $arg, $class ) ) {
				return false;
			}

			$arg = $arg->get_object();
		}

		$raw_item = $this->recognize( $arg );
		if ( !$raw_item )
			return false;

		return new $class( $raw_item );
	}

	/**
	 * @param object Raw object
	 */
	abstract protected function recognize( $arg );
}


class P2P_Side_Post extends P2P_Side {

	protected $item_type = 'P2P_Item_Post';

	function __construct( $query_vars ) {
		$this->query_vars = $query_vars;
	}

	public function get_object_type() {
		return 'post';
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

	function do_query( $args ) {
		return new WP_Query( $args );
	}

	function capture_query( $args ) {
		$q = new WP_Query;
		$q->_p2p_capture = true;

		$q->query( $args );

		return $q->_p2p_sql;
	}

	function get_list( $wp_query ) {
		$list = new P2P_List( $wp_query->posts, $this->item_type );

		$list->current_page = max( 1, $wp_query->get('paged') );
		$list->total_pages = $wp_query->max_num_pages;

		return $list;
	}

	function is_indeterminate( $side ) {
		$common = array_intersect(
			$this->query_vars['post_type'],
			$side->query_vars['post_type']
		);

		return !empty( $common );
	}

	protected function recognize( $arg ) {
		if ( is_object( $arg ) && !isset( $arg->post_type ) )
			return false;

		$post = get_post( $arg );

		if ( !is_object( $post ) )
			return false;

		if ( !$this->recognize_post_type( $post->post_type ) )
			return false;

		return $post;
	}

	public function recognize_post_type( $post_type ) {
		if ( !post_type_exists( $post_type ) )
			return false;

		return in_array( $post_type, $this->query_vars['post_type'] );
	}
}


class P2P_Side_Attachment extends P2P_Side_Post {

	protected $item_type = 'P2P_Item_Attachment';

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


class P2P_Side_User extends P2P_Side {

	protected $item_type = 'P2P_Item_User';

	function __construct( $query_vars ) {
		$this->query_vars = $query_vars;
	}

	function get_object_type() {
		return 'user';
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

	function translate_qv( $qv ) {
		if ( isset( $qv['p2p:include'] ) )
			$qv['include'] = _p2p_pluck( $qv, 'p2p:include' );

		if ( isset( $qv['p2p:exclude'] ) )
			$qv['exclude'] = _p2p_pluck( $qv, 'p2p:exclude' );

		if ( isset( $qv['p2p:search'] ) && $qv['p2p:search'] )
			$qv['search'] = '*' . _p2p_pluck( $qv, 'p2p:search' ) . '*';

		if ( isset( $qv['p2p:page'] ) && $qv['p2p:page'] > 0 ) {
			if ( isset( $qv['p2p:per_page'] ) && $qv['p2p:per_page'] > 0 ) {
				$qv['number'] = $qv['p2p:per_page'];
				$qv['offset'] = $qv['p2p:per_page'] * ( $qv['p2p:page'] - 1 );
			}
		}

		return $qv;
	}

	function do_query( $args ) {
		return new WP_User_Query( $args );
	}

	function capture_query( $args ) {
		$args['count_total'] = false;

		$uq = new WP_User_Query;
		$uq->_p2p_capture = true; // needed by P2P_URL_Query

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

	function get_list( $query ) {
		$list = new P2P_List( $query->get_results(), $this->item_type );

		$qv = $query->query_vars;

		if ( isset( $qv['p2p:page'] ) ) {
			$list->current_page = $qv['p2p:page'];
			$list->total_pages = ceil( $query->get_total() / $qv['p2p:per_page'] );
		}

		return $list;
	}

	function is_indeterminate( $side ) {
		return true;
	}

	function get_base_qv( $q ) {
		return array_merge( $this->query_vars, $q );
	}

	protected function recognize( $arg ) {
		if ( is_a( $arg, 'WP_User' ) )
			return $arg;

		return get_user_by( 'id', $arg );
	}
}

