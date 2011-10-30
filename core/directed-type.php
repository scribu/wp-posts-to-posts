<?php

class P2P_Directed_Connection_Type {

	protected $ctype;
	protected $direction;

	protected $cardinality;
	protected $other_cardinality;

	function __construct( $ctype, $direction ) {
		$this->ctype = $ctype;
		$this->direction = $direction;

		$this->set_cardinality();
	}

	protected function set_cardinality() {
		$parts = explode( '-', $this->ctype->cardinality );

		if ( 'to' == $this->direction )
			$parts = array_reverse( $parts );

		$this->cardinality = ( 'one' == $parts[2] ) ? 'one' : 'many';
		$this->other_cardinality = ( 'one' == $parts[0] ) ? 'one' : 'many';
	}

	function __get( $key ) {
		return $this->ctype->$key;
	}

	function __isset( $key ) {
		return isset( $this->ctype->$key );
	}

	public function get_direction() {
		return $this->direction;
	}

	public function set_direction( $direction ) {
		return $this->ctype->set_direction( $direction );
	}

	public function lose_direction() {
		return $this->ctype;
	}

	public function accepts_single_connection() {
		return 'one' == $this->cardinality;
	}

	public function get_title( $two_boxes = false ) {
		$key = ( 'to' == $this->direction ) ? 'to' : 'from';

		$title = $this->title[ $key ];

		if ( $two_boxes && $this->title['from'] == $this->title['to'] ) {
			$map = array(
				'from' => __( ' (from)', P2P_TEXTDOMAIN ),
				'to' => __( ' (to)', P2P_TEXTDOMAIN ),
			);

			$title .= $map[$key];
		}

		return $title;
	}

	public function get_current_post_type() {
		return 'to' == $this->direction ? $this->to : $this->from;
	}

	public function get_other_post_type() {
		return 'to' == $this->direction ? $this->from : $this->to;
	}

	public function get_sortby_field() {
		if ( !$this->sortable || 'any' == $this->direction )
			return false;

		if ( 'any' == $this->sortable || $this->direction == $this->sortable )
			return '_order_' . $this->direction;

		if ( 'from' == $this->direction )
			return $this->sortable;
	}

	private function get_base_qv() {
		$base_qv = ( 'from' == $this->direction ) ? $this->to_query_vars : $this->from_query_vars;

		return array_merge( $base_qv, array(
			'suppress_filters' => false,
			'ignore_sticky_posts' => true,
		) );
	}

	public function get_connected( $post_id, $extra_qv = array() ) {
		if ( $sortby = $this->get_sortby_field() ) {
			$order_args = array(
				'connected_orderby' => $sortby,
				'connected_order' => 'ASC',
				'connected_order_num' => true,
			);
		} else {
			$order_args = array();
		}

		$args = array_merge( $order_args, $extra_qv, $this->get_base_qv() );

		// don't completely overwrite 'connected_meta', but ensure that $this->data is added
		$args = array_merge_recursive( $args, array(
			'connected_meta' => $this->data
		) );

		$args['connected_query'] = array(
			'posts' => $post_id,
			'direction' => $this->direction
		);

		$args = apply_filters( 'p2p_connected_args', $args, $this );

		return new WP_Query( $args );
	}

	public function get_connectable( $post_id, $extra_qv = array() ) {
		$args = array_merge( $this->get_base_qv(), $extra_qv );

		if ( 'one' == $this->other_cardinality ) {
			$connected = $this->get_connected( 'any', array( 'fields' => 'ids' ) )->posts;
		} else if ( $this->prevent_duplicates ) {
			$connected = $this->get_connected( $post_id, array( 'fields' => 'ids' ) )->posts;
		}

		if ( !empty( $connected ) ) {
			$args = array_merge_recursive( $args, array(
				'post__not_in' => $connected
			) );
		}

		$args = apply_filters( 'p2p_connectable_args', $args, $this );

		return new WP_Query( $args );
	}

	public function get_p2p_id( $from, $to ) {
		$connected = $this->get_connected( $from, array( 'post__in' => array( $to ) ) );

		if ( !empty( $connected->posts ) )
			return (int) $connected->posts[0]->p2p_id;

		return false;
	}

	public function connect( $from, $to ) {
		if ( !get_post( $from ) || !get_post( $to ) )
			return false;

		$p2p_id = false;

		if ( 'one' == $this->cardinality ) {
			$connected = $this->get_connected( $from, array( 'fields' => 'ids' ) );
			if ( !empty( $connected->posts ) )
				return false;
		}

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

		foreach ( $connected->posts as $post )
			P2P_Storage::delete( $post->p2p_id );
	}
}

