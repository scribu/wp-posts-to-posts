<?php

class P2P_Constraint extends PHPUnit_Framework_Constraint {

	public function __construct( $description, $test_cb ) {
		$this->desc = $description;
		$this->test = $test_cb;
	}

	function matches( $arg ) {
		return call_user_func( $this->test, $arg );
	}

	function toString() {
		return $this->desc;
	}
}

