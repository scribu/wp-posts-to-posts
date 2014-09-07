<?php

class P2P_Side_Bpgroup extends P2P_Side {

	protected $item_type = 'P2P_Item_Bpgroup';

	function __construct( $query_vars ) {
		$this->query_vars = $query_vars;
	}

	function get_object_type() {
		return 'bpgroup';
	}

	function get_desc() {
		return __( 'Buddypress Group', P2P_TEXTDOMAIN );
	}

	function get_title() {
		return $this->get_desc();
	}

	function get_labels() {
		return (object) array(
			'singular_name' => __( 'Buddypress Group', P2P_TEXTDOMAIN ),
			'search_items' => __( 'Search Buddypress Groups', P2P_TEXTDOMAIN ),
			'not_found' => __( 'No Buddypress Groups found.', P2P_TEXTDOMAIN ),
		);
	}

	function can_edit_connections() {
		return true; //current_user_can( 'list_users' );
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
		return new BP_Groups_Template($args);
	}

	function capture_query( $args ) {
		$args['count_total'] = false;

		$uq = new BP_Groups_Group;
		$uq->_p2p_capture = true; // needed by P2P_URL_Query

		$r = wp_parse_args( $args, $defaults );

		$groups = BP_Groups_Group::get( array(
			'type'              => $r['type'],
			'user_id'           => $r['user_id'],
			'include'           => $r['include'],
			'exclude'           => $r['exclude'],
			'search_terms'      => $r['search_terms'],
			'meta_query'        => $r['meta_query'],
			'show_hidden'       => $r['show_hidden'],
			'per_page'          => $r['per_page'],
			'page'              => $r['page'],
			'populate_extras'   => $r['populate_extras'],
			'update_meta_cache' => $r['update_meta_cache'],
			'order'             => $r['order'],
			'orderby'           => $r['orderby'],
		) );
		
		return $groups;
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
		if ( is_a( $arg, 'BP_Groups_Group' ) )
			return $arg;

		return false;
	}
}

