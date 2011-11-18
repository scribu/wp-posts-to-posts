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

	public function get_title() {
		$key = ( 'to' == $this->direction ) ? 'to' : 'from';

		return $this->title[ $key ];
	}

	public function get_current_post_type() {
		return 'to' == $this->direction ? $this->to : $this->from;
	}

	public function get_other_post_type() {
		return 'to' == $this->direction ? $this->from : $this->to;
	}

	private function get_base_qv() {
		$base_qv = ( 'from' == $this->direction ) ? $this->to_query_vars : $this->from_query_vars;

		return array_merge( $base_qv, array(
			'suppress_filters' => false,
			'ignore_sticky_posts' => true,
		) );
	}

	/**
	 * Get a list of posts that are connected to a given post.
	 *
	 * @param int|array $post_id A post id or an array of post ids.
	 * @param array $extra_qv Additional query variables to use.
	 *
	 * @return A WP_Query instance on success.
	 */
	public function get_connected( $post_id, $extra_qv = array() ) {
		return new WP_Query( $this->get_connected_args( $post_id, $extra_qv ) );
	}

	public function get_connected_args( $post_id, $extra_qv = array() ) {
		$args = array_merge( $extra_qv, $this->get_base_qv() );

		// don't completely overwrite 'connected_meta', but ensure that $this->data is added
		$args = array_merge_recursive( $args, array(
			'connected_meta' => $this->data
		) );

		$args['connected_query'] = array(
			'posts' => $post_id,
			'direction' => $this->direction
		);

		return apply_filters( 'p2p_connected_args', $args, $this, $post_id );
	}

	/**
	 * Get a list of posts that could be connected to a given post.
	 *
	 * @param int $post_id A post id.
	 * @param array $extra_qv Additional query variables to use.
	 *
	 * @return bool|object False on failure; A WP_Query instance on success.
	 */
	public function get_connectable( $post_id, $extra_qv = array() ) {
		$args = array_merge( $this->get_base_qv(), $extra_qv );

		if ( 'one' == $this->other_cardinality ) {
			$to_check = 'any';
		} elseif ( $this->prevent_duplicates ) {
			$to_check = $post_id;
		}

		if ( isset( $to_check ) ) {
			$connected = $this->get_connected( $to_check, array( 'fields' => 'ids' ) )->posts;

			if ( !empty( $connected ) ) {
				$args = array_merge_recursive( $args, array(
					'post__not_in' => $connected
				) );
			}
		}

		$args = apply_filters( 'p2p_connectable_args', $args, $this, $post_id );

		return new WP_Query( $args );
	}

	public function get_p2p_id( $from, $to ) {
		$connected = $this->get_connected( $from, array( 'post__in' => array( $to ) ) );

		if ( !empty( $connected->posts ) )
			return (int) $connected->posts[0]->p2p_id;

		return false;
	}

	/**
	 * Connect two posts.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 *
	 * @return int p2p_id
	 */
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

	/**
	 * Disconnect two posts.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 */
	public function disconnect( $from, $to ) {
		return P2P_Storage::delete( $this->get_p2p_id( $from, $to ) );
	}

	/**
	 * Delete all connections for a certain post.
	 *
	 * @param int The post id.
	 */
	public function disconnect_all( $from ) {
		$connected = $this->get_connected( $from );

		foreach ( $connected->posts as $post )
			P2P_Storage::delete( $post->p2p_id );
	}
}

