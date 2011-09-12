<?php

/**
 * Takes care of everything related to connection data: currently connected posts, potentially connected posts etc.
 */
class P2P_Box_Data {
	public $box_id;

	public $direction;

	protected $reversed;
	protected $args;

	public function __construct( $args, $direction, $box_id ) {
		$this->box_id = $box_id;

		$this->args = $args;

		$this->direction = $direction;

		$this->reversed = ( 'to' == $direction );

		if ( $this->reversed )
			list( $this->to, $this->from ) = array( $this->from, $this->to );
	}

	function __get( $key ) {
		if ( 'title' == $key ) {
			$title = $this->args['title'];

			if ( is_array( $title ) ) {
				$key = $this->reversed ? 'to' : 'from';

				if ( isset( $title[ $key ] ) )
					$title = $title[ $key ];
				else
					$title = '';
			}

			return $title;
		}

		return $this->args[$key];
	}

	public function create_post( $title ) {
		return wp_insert_post( $this->get_new_post_args( $title ) );
	}

	protected function get_new_post_args( $title ) {
		return array(
			'post_title' => $title,
			'post_author' => get_current_user_id(),
			'post_type' => $this->to
		);
	}

	public function get_connection_candidates( $current_post_id, $page, $search ) {
		$query = new WP_Query( $this->get_query_vars( $current_post_id, $page, $search ) );

		return (object) array(
			'posts' => $query->posts,
			'current_page' => max( 1, $query->get('paged') ),
			'total_pages' => $query->max_num_pages
		);
	}

	protected function get_query_vars( $post_id, $page, $search ) {
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
			$args['post__not_in'] = P2P_Connections::get( $post_id, $this->direction, $this->data );

		return $args;
	}

	function _search_by_title( $sql, $wp_query ) {
		if ( $wp_query->is_search ) {
			list( $sql ) = explode( ' OR ', $sql, 2 );
			return $sql . '))';
		}

		return $sql;
	}

	function get_connected_ids( $post_id ) {
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

		$q = new WP_Query( $args );

		return scb_list_fold( $q->posts, 'p2p_id', 'ID' );
	}

	function connect( $from, $to ) {
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

	function disconnect( $post_id ) {
		p2p_disconnect( $post_id, $this->direction, $this->data );
	}

	function delete_connection( $p2p_id ) {
		p2p_delete_connection( $p2p_id );
	}
}

