<?php

_p2p_load_admin();

require_once __DIR__ . '/constraints.php';
require_once __DIR__ . '/mock-factory.php';


class P2P_Tests_Admin_Factory extends WP_UnitTestCase {

	function setUp() {
		$this->mock = new P2P_Factory_Mock;
		parent::setUp();
	}

	function test_factory_none() {
		$ctype = p2p_register_connection_type( array(
			'name' => __FUNCTION__,
		) );

		$this->assertEmpty( $this->mock->get_queue() );
	}

	function test_factory_any() {
		$ctype = p2p_register_connection_type( array(
			'name' => __FUNCTION__,
			'from' => 'user',
			'to' => 'page',
			'admin_mock' => 'any',
		) );

		$this->assertNotEmpty( $this->mock->get_queue() );

		$this->assertEquals( 0, count( $this->mock->add_items( 'post', 'post' ) ) );

		$this->assertEquals( 1, count( $this->mock->add_items( 'post', 'page' ) ) );

		$this->assertEquals( 1, count( $this->mock->add_items( 'user' ) ) );
	}

	function test_factory_from() {
		$ctype = p2p_register_connection_type( array(
			'name' => __FUNCTION__,
			'from' => 'user',
			'to' => 'page',
			'admin_mock' => 'from',
		) );

		$this->assertNotEmpty( $this->mock->get_queue() );

		$this->assertEquals( 0, count( $this->mock->add_items( 'post', 'post' ) ) );

		$this->assertEquals( 0, count( $this->mock->add_items( 'post', 'page' ) ) );

		$this->assertEquals( 1, count( $this->mock->add_items( 'user' ) ) );
	}

	function test_factory_to() {
		$ctype = p2p_register_connection_type( array(
			'name' => __FUNCTION__,
			'from' => 'user',
			'to' => 'page',
			'admin_mock' => 'to',
		) );

		$this->assertNotEmpty( $this->mock->get_queue() );

		$this->assertEquals( 0, count( $this->mock->add_items( 'post', 'post' ) ) );

		$this->assertEquals( 1, count( $this->mock->add_items( 'post', 'page' ) ) );

		$this->assertEquals( 0, count( $this->mock->add_items( 'user' ) ) );
	}

	function test_factory_extra_args() {
		$ctype = p2p_register_connection_type( array(
			'name' => __FUNCTION__,
			'from' => 'user',
			'to' => 'page',
			'admin_mock' => array(
				'foo' => 'bar',
			),
		) );

		$this->assertNotEmpty( $this->mock->get_queue() );

		$this->assertEquals( 0, count( $this->mock->add_items( 'post', 'post' ) ) );

		$this->assertEquals( 1, count( $this->mock->add_items( 'post', 'page' ) ) );

		$this->assertEquals( 1, count( $this->mock->add_items( 'user' ) ) );
	}
}

