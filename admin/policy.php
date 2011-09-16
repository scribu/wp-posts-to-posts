<?php

/**
 * A connection-type aware API
 */
class P2P_Connections_Policy {

	protected $args;

	public function __construct( $args ) {
		$this->args = $args;
	}

	public function __get( $key ) {
		return $this->args[$key];
	}

	public function create_post( $title ) {
		$args = array(
			'post_title' => $title,
			'post_author' => get_current_user_id(),
			'post_type' => $this->to
		);

		$args = apply_filters( 'p2p_new_post_args', $args, $this->args );

		return wp_insert_post( $args );
	}

	public function get_connection_candidates( $current_post_id, $page, $search ) {
		$args = array(
			'paged' => $page,
			'post_type' => $this->to,
			'post_status' => 'any',
			'posts_per_page' => 5,
			'suppress_filters' => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false
		);

		if ( $search ) {
			add_filter( 'posts_search', array( __CLASS__, '_search_by_title' ), 10, 2 );
			$args['s'] = $search;
		}

		if ( $this->prevent_duplicates )
			$args['post__not_in'] = P2P_Connections::get( $current_post_id, $this->direction, $this->data );

		$args = apply_filters( 'p2p_possible_connections_args', $args, $this->args );

		$query = new WP_Query( $args );

		return (object) array(
			'posts' => $query->posts,
			'current_page' => max( 1, $query->get('paged') ),
			'total_pages' => $query->max_num_pages
		);
	}

	function _search_by_title( $sql, $wp_query ) {
		if ( $wp_query->is_search ) {
			list( $sql ) = explode( ' OR ', $sql, 2 );
			return $sql . '))';
		}

		return $sql;
	}

	public function get_current_connections( $post_id ) {
		$args = array(
			array_search( $this->direction, P2P_Query::$qv_map ) => $post_id,
			'connected_meta' => $this->data,
			'post_type'=> $this->to,
			'post_status' => 'any',
			'nopaging' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'ignore_sticky_posts' => true,
		);

		if ( $this->sortable ) {
			$args['connected_orderby'] = $this->sortable;
			$args['connected_order'] = 'ASC';
			$args['connected_order_num'] = true;
		}

		$args = apply_filters( 'p2p_current_connections_args', $args, $this->args );

		$q = new WP_Query( $args );

		return scb_list_fold( $q->posts, 'p2p_id', 'ID' );
	}

	public function connect( $from, $to ) {
		$args = array( $from, $to );

		if ( $this->reversed )
			$args = array_reverse( $args );

		$p2p_id = false;

		if ( $this->prevent_duplicates ) {
			$p2p_ids = P2P_Connections::get( $args[0], $args[1], $this->data );

			if ( !empty( $p2p_ids ) )
				$p2p_id = $p2p_ids[0];
		}

		if ( !$p2p_id ) {
			$p2p_id = P2P_Connections::connect( $args[0], $args[1], $this->data );
		}

		return $p2p_id;
	}

	public function disconnect( $post_id ) {
		p2p_disconnect( $post_id, $this->direction, $this->data );
	}

	public function delete_connection( $p2p_id ) {
		p2p_delete_connection( $p2p_id );
	}
}

