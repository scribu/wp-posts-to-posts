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
	 * @return bool|P2P_Item
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

