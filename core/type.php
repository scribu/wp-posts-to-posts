<?php

class P2P_Connection_Type {

	public $indeterminate;

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

		if ( $this->object['from'] == $this->object['to'] ) {
			$this->indeterminate = $this->side['from']->is_indeterminate( $this->side['to'] );
		} else {
			$args['self_connections'] = true;
		}

		$this->set_cardinality( _p2p_pluck( $args, 'cardinality' ) );

		$this->set_labels( $args );

		$this->title = $this->expand_title( _p2p_pluck( $args, 'title' ) );

		$this->fields = $this->expand_fields( _p2p_pluck( $args, 'fields' ) );

		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}
	}

	private function expand_fields( $fields ) {
		foreach ( $fields as &$field_args )
		{
			if ( !is_array( $field_args ) )
				$field_args = array( 'title' => $field_args );

			if ( !isset( $field_args['type'] ) )
			{
				$field_args['type'] = isset( $field_args['values'] ) ? 'select' : 'text';
			}
			elseif ( 'checkbox' == $field_args['type'] && !isset( $field_args['values'] ) )
			{
				$field_args['values'] = array( true => ' ' );
			}
		}

		return $fields;
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
	 * Attempt to guess direction based on a parameter.
	 *
	 * @param mixed A post type, object or object id.
	 * @param bool Whether to return an instance of P2P_Directed_Connection_Type or just the direction
	 *
	 * @return bool|object|string False on failure, P2P_Directed_Connection_Type instance or direction on success.
	 */
	public function find_direction( $arg, $instantiate = true ) {
		if ( is_array( $arg ) )
			$arg = reset( $arg );

		foreach ( array( 'from', 'to' ) as $direction ) {
			if ( !$this->side[ $direction ]->item_recognize( $arg ) )
				continue;

			if ( $this->indeterminate )
				$direction = $this->reciprocal ? 'any' : 'from';

			return $this->set_direction( $direction, $instantiate );
		}

		return false;
	}

	// Used in each_connected()
	private function find_direction_multiple( $post_types ) {
		$possible_directions = array();

		foreach ( array( 'from', 'to' ) as $direction ) {
			if ( 'post' == $this->object[$direction] ) {
				foreach ( $post_types as $post_type ) {
					if ( !$this->side[ $direction ]->item_recognize( $post_type ) ) {
						$possible_directions[] = $direction;
						break;
					}
				}
			}
		}

		if ( empty( $possible_directions ) )
			return false;

		if ( count( $possible_directions ) > 1 )
			return 'any';

		return reset( $possible_directions );
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

		// The direction needs to be based on the second parameter,
		// so that it's consistent with $this->connect( $from, $to ) etc.
		$directed = $this->find_direction( $to );
		if ( !$directed )
			return false;

		$key = $directed->get_orderby_key();
		if ( !$key )
			return false;

		$p2p_id = $directed->get_p2p_id( $to, $from );
		if ( !$p2p_id )
			return false;

		$order = (int) p2p_get_meta( $p2p_id, $key, true );

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
	 * Optimized inner query, after the outer query was executed.
	 *
	 * Populates each of the outer querie's $post objects with a 'connected' property, containing a list of connected posts
	 *
	 * @param object|array $items WP_Query instance or list of post objects
	 * @param string|array $extra_qv Additional query vars for the inner query.
	 * @param string $prop_name The name of the property used to store the list of connected items on each post object.
	 */
	public function each_connected( $items, $extra_qv = array(), $prop_name = 'connected' ) {
		if ( is_a( $items, 'WP_Query' ) )
			$items =& $items->posts;

		if ( empty( $items ) || !is_object( $items[0] ) )
			return;

		$post_types = array_unique( wp_list_pluck( $items, 'post_type' ) );

		if ( count( $post_types ) > 1 ) {
			$direction = $this->find_direction_multiple( $post_types );
			$extra_qv['post_type'] = 'any';
		} else {
			$direction = $this->find_direction( $post_types[0], false );
		}

		if ( !$direction )
			return false;

		$directed = $this->set_direction( $direction );

		// ignore pagination
		foreach ( array( 'showposts', 'posts_per_page', 'posts_per_archive_page' ) as $disabled_qv ) {
			if ( isset( $extra_qv[ $disabled_qv ] ) ) {
				trigger_error( "Can't use '$disabled_qv' in an inner query", E_USER_WARNING );
			}
		}
		$extra_qv['nopaging'] = true;

		$q = $directed->get_connected( $items, $extra_qv, 'abstract' );

		p2p_distribute_connected( $items, $q->items, $prop_name );
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

