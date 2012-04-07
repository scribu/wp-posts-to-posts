<?php

class Generic_Connection_Type {

	public $indeterminate = false;

	public $object;

	public $side;

	public $cardinality;

	public $title;

	public $labels;

	public function __construct( $args ) {
		foreach ( array( 'from', 'to' ) as $direction ) {
			$this->object[ $direction ] = _p2p_pluck( $args, $direction . '_object' );

			$class = 'P2P_Side_' . ucfirst( $this->object[ $direction ] );

			$this->side[ $direction ] = new $class( _p2p_pluck( $args, $direction . '_query_vars' ) );
		}

		$this->set_cardinality( _p2p_pluck( $args, 'cardinality' ) );

		$this->set_labels( $args );

		$this->title = $this->expand_title( _p2p_pluck( $args, 'title' ) );

		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}
	}

	private function set_cardinality( $cardinality ) {
		$parts = explode( '-', $cardinality );

		$this->cardinality['from'] = $parts[0];
		$this->cardinality['to'] = $parts[2];

		foreach ( $this->cardinality as $key => &$value ) {
			if ( 'one' != $value )
				$value = 'many';
		}
	}

	private function set_labels( &$args ) {
		foreach ( array( 'from', 'to' ) as $key ) {
			$labels = _p2p_pluck( $args, $key . '_labels' );

			if ( empty( $labels ) )
				$labels = $this->side[ $key ]->get_labels();
			else
				$labels = (object) $labels;

			$this->labels[ $key ] = $labels;
		}
	}

	private function expand_title( $title ) {
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

	public function __call( $method, $args ) {
		$directed = $this->find_direction( $args[0] );
		if ( !$directed ) {
			trigger_error( sprintf( "Can't determine direction for '%s' type.", $this->name ), E_USER_WARNING );
			return false;
		}

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
	public function find_direction( $arg, $instantiate = true ) {
		foreach ( array( 'from', 'to' ) as $direction ) {
			if ( !$this->side[ $direction ]->item_recognize( $arg ) )
				continue;

			if ( $this->indeterminate )
				$direction = $this->reciprocal ? 'any' : 'from';

			return $this->set_direction( $direction, $instantiate );
		}

		return false;
	}

	/** Alias for get_prev() */
	public function get_previous( $from, $to ) {
		return $this->get_prev( $from, $to );
	}

	/**
	 * Get the previous post in an ordered connection.
	 *
	 * @param int The first end of the connection.
	 * @param int The second end of the connection.
	 *
	 * @return bool|object False on failure, post object on success
	 */
	public function get_prev( $from, $to ) {
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
		), 'abstract' );

		return _p2p_first( $adjacent->items );
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

		$extra_qv['fields'] = 'ids';

		$connected = $this->get_connected( $post_id, $extra_qv, 'abstract' );
		if ( !$connected )
			return false;

		if ( empty( $connected->items ) )
			return new WP_Query;

		return new WP_Query( array(
			'connected_type' => $this->name,
			'connected_items' => $connected->items,
			'post__not_in' => $post_id,
		) );
	}

	/**
	 * Optimized inner query, after the outer query was executed.
	 *
	 * Populates each of the outer querie's $post objects with a 'connected' property, containing a list of connected posts
	 *
	 * @param object $query WP_Query instance.
	 * @param string|array $extra_qv Additional query vars for the inner query.
	 * @param string $prop_name The name of the property used to store the list of connected items on each post object.
	 */
	public function each_connected( $query, $extra_qv = array(), $prop_name = 'connected' ) {
		if ( empty( $query->posts ) || !is_object( $query->posts[0] ) )
			return;

		$post_type = $query->get( 'post_type' );
		if ( empty( $post_type ) )
			$post_type = 'post';

		$directed = $this->find_direction( $post_type );
		if ( !$directed )
			return false;

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

		$q = $directed->get_connected( array_keys( $posts ), $extra_qv, 'abstract' );

		foreach ( $q->items as $inner_item ) {
			if ( $inner_item->ID == $inner_item->p2p_from ) {
				$outer_item_id = $inner_item->p2p_to;
			} elseif ( $inner_item->ID == $inner_item->p2p_to ) {
				$outer_item_id = $inner_item->p2p_from;
			} else {
				trigger_error( "Corrupted data for item $inner_item->ID", E_USER_WARNING );
				continue;
			}

			array_push( $posts[ $outer_item_id ]->$prop_name, $inner_item );
		}
	}

	public function get_desc() {
		foreach ( array( 'from', 'to' ) as $key ) {
			$$key = $this->side[ $key ]->get_desc();
		}

		if ( $this->indeterminate )
			$arrow = '&harr;';
		else
			$arrow = '&rarr;';

		$label = "$from $arrow $to";

		$title = $this->title[ 'from' ];

		if ( $title )
			$label .= " ($title)";

		return $label;
	}
}


class P2P_Connection_Type extends Generic_Connection_Type {

	public function __construct( $args ) {
		parent::__construct( $args );

		$common = array_intersect( $this->from, $this->to );

		if ( !empty( $common ) )
			$this->indeterminate = true;
	}

	public function __get( $key ) {
		if ( 'from' == $key || 'to' == $key )
			return $this->side[ $key ]->post_type;
	}
}

