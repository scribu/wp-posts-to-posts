<?php

// A decorator for the WP_Query class
class scbQuery {
	protected $wp_query;

	public function __construct($qv = '', $debug = false) {
		if ( $debug )
			add_filter('posts_request', array($this, '_debug'), 999);

		if ( empty($qv) ) {
			$this->_add_filters(true);
		} else {
			$this->_add_filters();
			$this->wp_query = new WP_Query($qv);
			$this->_remove_filters();
		}
	}

/*
	final public function __get($key) {
		return $this->wp_query->$key;
	}

	final public function __call($name, $args) {
		return call_user_func_array(array($this->wp_query, $name), $args);
	}
*/

	public function _debug($query) {
		remove_filter(current_filter(), array($this, __FUNCTION__), 999);

		debug($query);

		return $query;
	}

	public function _add_filters($runonce = false) {
		if ( $runonce )
			foreach ( $this->_find_filters() as $filter )
				add_filter($filter, array($this, '_dispatch'), 10, 10);
		else
			foreach ( $this->_find_filters() as $filter )
				add_filter($filter, array($this, $filter), 10, 10);
	}

	public function _dispatch($value) {
		$filter = current_filter();

		remove_filter($filter, array($this, '_dispatch'), 10, 10);

		return $this->$filter($value);
	}

	public function _remove_filters() {
		foreach ( $this->_find_filters() as $filter )
			remove_filter($filter, array($this, $filter), 10, 10);
	}

	private function _find_filters() {
		$filters = array();

		foreach ( _scb_get_public_methods($this) as $method )
			if ( '_' != substr($method, 0, 1) )
				$filters[] = $method;

		return $filters;
	}
}

// Current scope is lost while calling external function
function _scb_get_public_methods($class) {
	return get_class_methods($class);
}

