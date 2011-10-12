<?php

class P2P_Directed_Connection_Type {

	protected $ctype;
	protected $direction;

	function __construct( $ctype, $direction ) {
		$this->ctype = $ctype;
		$this->direction = $direction;
	}

	function __get( $key ) {
		if ( 'direction' == $key )
			return $this->direction;

		return $this->ctype->$key;
	}

	public function lose_direction() {
		return $this->ctype;
	}

	public function get_title() {
		$title = $this->title;

		if ( is_array( $title ) ) {
			$key = ( 'to' == $this->direction ) ? 'to' : 'from';

			if ( isset( $title[ $key ] ) )
				$title = $title[ $key ];
			else
				$title = '';
		}

		return $title;
	}

	public function get_other_post_type() {
		return 'from' == $this->direction ? $this->to : $this->from;
	}

	public function can_create_post() {
		$ptype = $this->get_other_post_type();

		if ( count( $ptype ) > 1 )
			return false;

		return current_user_can( get_post_type_object( $ptype[0] )->cap->edit_posts );
	}

	public function is_sortable() {
		return $this->sortable && 'from' == $this->direction;
	}

	private function get_base_args( $extra_qv ) {
		$base_qv = ( 'from' == $this->direction ) ? $this->to_query_vars : $this->from_query_vars;

		return array_merge( $extra_qv, $base_qv, array(
			'suppress_filters' => false,
			'ignore_sticky_posts' => true
		) );
	}

	public function get_connected( $post_id, $extra_qv = array() ) {
		$args = $this->get_base_args( $extra_qv );

		_p2p_append( $args, array(
			'connected' => $post_id,
			'connected_direction' => $this->direction,
			'connected_meta' => $this->data,
		) );

		if ( $this->is_sortable() ) {
			_p2p_append( $args, array(
				'connected_orderby' => $this->sortable,
				'connected_order' => 'ASC',
				'connected_order_num' => true,
			) );
		}

		$args = apply_filters( 'p2p_connected_args', $args, $this );

		return new WP_Query( $args );
	}

	public function get_connectable( $post_id, $extra_qv = array() ) {
		$args = $this->get_base_args( $extra_qv );

		if ( $this->prevent_duplicates ) {
			$connected = $this->get_connected( $post_id, array( 'fields' => 'ids' ) );

			if ( !isset( $args['post__not_in'] ) ) {
				$args['post__not_in'] = array();
			}

			_p2p_append( $args['post__not_in'], $connected->posts );
		}

		$args = apply_filters( 'p2p_connectable_args', $args, $this );

		return new WP_Query( $args );
	}

	public function get_p2p_id( $from, $to ) {
		$connected = $this->get_connected( $from, array( 'post__in' => array( $to ) ) );

		if ( !empty( $connected->posts ) )
			return $connected->posts[0]->p2p_id;

		return false;
	}

	public function connect( $from, $to ) {
		if ( !get_post( $from ) || !get_post( $to ) )
			return false;

		$p2p_id = false;

		if ( $this->prevent_duplicates ) {
			$p2p_id = $this->get_p2p_id( $from, $to );
		}

		if ( !$p2p_id ) {
			$args = array( $from, $to );

			if ( 'to' == $this->direction )
				$args = array_reverse( $args );

			$p2p_id = P2P_Storage::connect( $args[0], $args[1], $this->data );
		}

		return $p2p_id;
	}

	public function disconnect( $from, $to ) {
		return P2P_Storage::delete( $this->get_p2p_id( $from, $to ) );
	}

	public function disconnect_all( $from ) {
		$connected = $this->get_connected( $from );

		foreach ( wp_list_pluck( $connected->posts, 'p2p_id' ) as $p2p_id )
			P2P_Storage::delete( $p2p_id );
	}
}

