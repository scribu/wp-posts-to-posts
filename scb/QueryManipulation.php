<?php

class scbQueryManipulation {

	private $bits = array();
	private $wp_query;

	private static $filters = array(
		'posts_where',
		'posts_join',
		'posts_groupby',
		'posts_orderby',
		'posts_distinct',
		'post_limits',
		'posts_fields'
	);

	public function __construct( $callback, $once = true ) {
		$this->callback = $callback;

		$this->enable();

		if ( !$once )
			return;

		add_filter( 'posts_request', array( $this, '_disable' ) );
	}

	function _disable( $request ) {
		remove_filter( 'posts_request', array( $this, '_disable' ) );

		$this->disable();

		return $request;
	}

	public function enable() {
		foreach ( self::$filters as $filter ) {
			add_filter( $filter, array( $this, 'collect' ), 999, 2 );
			add_filter( $filter . '_request' , array( $this, 'update' ), 9 );
		}

		add_action( 'posts_selection' , array( $this, 'alter' ) );
	}

	public function disable() {
		foreach ( self::$filters as $filter ) {
			remove_filter( $filter, array( $this, 'collect' ), 999, 2 );
			remove_filter( $filter . '_request' , array( $this, 'update' ), 9 );
		}

		remove_action( 'posts_selection' , array( $this, 'alter' ) );
	}

	function collect( $value, $wp_query ) {
		// remove 'posts_'
		$key = explode( '_', current_filter() );
		$key = array_slice( $key, 1 );
		$key = implode( '_', $key );

		$this->bits[ $key ] = $value;

		$this->wp_query = $wp_query;

		return $value;
	}

	function alter( $query ) {
		$this->bits = call_user_func( $this->callback, $this->bits, $this->wp_query );
	}

	function update( $value ) {
		// remove 'posts_' and '_request'
		$key = explode( '_', current_filter() );
		$key = array_slice( $key, 1, -1 );
		$key = implode( '_', $key );

		return $this->bits[ $key ];
	}
}

