<?php

class Generic_Connection_Type {

	public $indeterminate = false;

	public $side;

	public $cardinality;

	public $title;

	protected $args;

	public function __construct( $sides, $args ) {
		$this->side = $sides;

		$this->args = $args;

		$this->set_cardinality();

		$this->title = $this->expand_title( _p2p_pluck( $this->args, 'title' ) );
	}

	protected function set_cardinality() {
		$parts = explode( '-', _p2p_pluck( $this->args, 'cardinality' ) );

		$this->cardinality['from'] = $parts[0];
		$this->cardinality['to'] = $parts[2];

		foreach ( $this->cardinality as $key => &$value ) {
			if ( 'one' != $value )
				$value = 'many';
		}
	}

	private function expand_title( $title ) {
		if ( !$title )
			$title = array();

		if ( $title && !is_array( $title ) ) {
			return array(
				'from' => $title,
				'to' => $title,
			);
		}

		foreach ( array( 'from', 'to' ) as $key ) {
			if ( isset( $title[$key] ) )
				continue;

			$other_key = ( 'from' == $key ) ? 'to' : 'from';

			$title[$key] = sprintf(
				__( 'Connected %s', P2P_TEXTDOMAIN ),
				$this->side[ $other_key ]->get_title()
			);
		}

		return $title;
	}

	public function __get( $key ) {
		return $this->args[$key];
	}

	public function __isset( $key ) {
		return isset( $this->args[$key] );
	}

	public function __call( $method, $args ) {
		$directed = $this->find_direction( $args[0] );
		if ( !$directed )
			return false;

		return call_user_func_array( array( $directed, $method ), $args );
	}

	/**
	 * Set the direction.
	 *
	 * @param string $direction Can be 'from', 'to' or 'any'.
	 *
	 * @return object P2P_Directed_Connection_Type instance
	 */
	public function set_direction( $direction, $instantiate = true ) {
		if ( !in_array( $direction, array( 'from', 'to', 'any' ) ) )
			return false;

		if ( $instantiate )
			return new P2P_Directed_Connection_Type( $this, $direction );

		return $direction;
	}

	/**
	 * Attempt to guess direction based on a post id or post type.
	 *
	 * @param int|string $arg A post id or a post type.
	 * @param bool Whether to return an instance of P2P_Directed_Connection_Type or just the direction
	 *
	 * @return bool|object|string False on failure, P2P_Directed_Connection_Type instance or direction on success.
	 */
	public function find_direction( $arg, $instantiate = true, $object_type = false ) {
		if ( $object_type ) {
			$opposite_side = P2P_Util::choose_side( $object_type,
				$this->side['from']->object,
				$this->side['to']->object
			);

			if ( !$opposite_side )
				return false;

			if ( 'any' != $opposite_side )
				return $this->set_direction( $opposite_side, $instantiate );
		}

		$post_type = P2P_Util::find_post_type( $arg );

		foreach ( array( 'from', 'to' ) as $direction ) {
			$side = $this->side[ $direction ];

			if ( 'post' != $side->object )
				continue;

			if ( !in_array( $post_type, $side->post_type ) )
				continue;

			if ( $this->indeterminate )
				$direction = $this->reciprocal ? 'any' : 'from';

			return $this->set_direction( $direction, $instantiate );
		}

		return false;
	}
}


class P2P_Connection_Type extends Generic_Connection_Type {

	public function __construct( $sides, $args ) {
		parent::__construct( $sides, $args );

		$common = array_intersect( $this->from, $this->to );

		if ( !empty( $common ) )
			$this->indeterminate = true;
	}

	public function __get( $key ) {
		if ( 'from' == $key || 'to' == $key )
			return $this->side[ $key ]->post_type;

		return $this->args[$key];
	}

	/**
	 * Optimized inner query, after the outer query was executed.
	 *
	 * Populates each of the outer querie's $post objects with a 'connected' property, containing a list of connected posts
	 *
	 * @param object $query WP_Query instance.
	 * @param string|array $extra_qv Additional query vars for the inner query.
	 */
	public function each_connected( $query, $extra_qv = array() ) {
		if ( empty( $query->posts ) || !is_object( $query->posts[0] ) )
			return;

		$post_type = $query->get( 'post_type' );
		if ( empty( $post_type ) )
			$post_type = 'post';

		$directed = $this->find_direction( $post_type );
		if ( !$directed )
			return false;

		$prop_name = 'connected';

		$posts = array();

		foreach ( $query->posts as $post ) {
			$post->$prop_name = array();
			$posts[ $post->ID ] = $post;
		}

		// ignore pagination
		foreach ( array( 'showposts', 'posts_per_page', 'posts_per_archive_page' ) as $disabled_qv ) {
			if ( isset( $extra_qv[ $disabled_qv ] ) ) {
				trigger_error( "Can't use '$disabled_qv' in an inner query", E_USER_WARNING );
			}
		}
		$extra_qv['nopaging'] = true;

		$q = $directed->get_connected( array_keys( $posts ), $extra_qv );

		foreach ( $q->posts as $inner_post ) {
			if ( $inner_post->ID == $inner_post->p2p_from )
				$outer_post_id = $inner_post->p2p_to;
			elseif ( $inner_post->ID == $inner_post->p2p_to )
				$outer_post_id = $inner_post->p2p_from;
			else {
				trigger_error( "Corrupted data for post $inner_post->ID", E_USER_WARNING );
				continue;
			}

			if ( $outer_post_id == $inner_post->ID ) {
				trigger_error( 'Post connected to itself.', E_USER_WARNING );
				continue;
			}

			array_push( $posts[ $outer_post_id ]->$prop_name, $inner_post );
		}
	}

	/**
	 * Get a list of posts connected to other posts connected to a post.
	 *
	 * @param int|array $post_id A post id or array of post ids
	 * @param array $extra_qv Additional query variables to use.
	 *
	 * @return bool|object False on failure; A WP_Query instance on success.
	 */
	public function get_related( $post_id, $extra_qv = array() ) {
		$post_id = (array) $post_id;

		$connected = $this->get_connected( $post_id, $extra_qv );
		if ( !$connected )
			return false;

		if ( !$connected->have_posts() )
			return $connected;

		$connected_ids = wp_list_pluck( $connected->posts, 'ID' );

		return $this->get_connected( $connected_ids, array(
			'post__not_in' => $post_id,
		) );
	}

	/**
	 * Get the previous post in an ordered connection.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 *
	 * @return bool|object False on failure, post object on success
	 */
	public function get_previous( $from, $to ) {
		return $this->get_adjacent( $from, $to, -1 );
	}

	/**
	 * Get the next post in an ordered connection.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 *
	 * @return bool|object False on failure, post object on success
	 */
	public function get_next( $from, $to ) {
		return $this->get_adjacent( $from, $to, +1 );
	}

	/**
	 * Get another post in an ordered connection.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 * @param int The position relative to the first parameter
	 *
	 * @return bool|object False on failure, post object on success
	 */
	public function get_adjacent( $from, $to, $which ) {
		$directed = $this->find_direction( $to );
		if ( !$directed )
			return false;

		if ( !method_exists( $directed, 'get_orderby_key' ) )
			return false;

		$p2p_id = $directed->get_p2p_id( $to, $from );
		if ( !$p2p_id )
			return false;

		$order = (int) p2p_get_meta( $p2p_id, $directed->get_orderby_key(), true );

		$adjacent = $directed->get_connected( $to, array(
			'connected_meta' => array(
				array(
					'key' => $directed->get_orderby_key(),
					'value' => $order + $which
				)
			)
		) )->posts;

		if ( empty( $adjacent ) )
			return false;

		return $adjacent[0];
	}
}

