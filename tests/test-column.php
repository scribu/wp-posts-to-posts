<?php

_p2p_load_admin();

require_once __DIR__ . '/mock-column.php';


class P2P_Tests_Column extends WP_UnitTestCase {

	function test_default_title() {
		$ctype = p2p_register_connection_type( array(
			'name' => __FUNCTION__,
			'from' => 'post',
			'to' => 'page',
			'title' => array( 'from' => 'POST', 'to' => 'PAGE' )
		) );

		// from
		$mock = new P2P_Column_Mock( $ctype->set_direction( 'from' ) );

		$columns = $mock->add_column( array() );

		$this->assertCount( 1, $columns );
		$this->assertEquals( 'POST', reset( $columns ) );

		// to
		$mock = new P2P_Column_Mock( $ctype->set_direction( 'to' ) );

		$columns = $mock->add_column( array() );

		$this->assertCount( 1, $columns );
		$this->assertEquals( 'PAGE', reset( $columns ) );
	}

	function test_column_title() {
		$ctype = p2p_register_connection_type( array(
			'name' => __FUNCTION__,
			'from' => 'post',
			'to' => 'page',
			'from_labels' => array(
				'column_title' => 'POST COLUMN'
			),
			'to_labels' => array(
				'column_title' => 'PAGE COLUMN'
			)
		) );

		// from
		$mock = new P2P_Column_Mock( $ctype->set_direction( 'from' ) );

		$columns = $mock->add_column( array() );

		$this->assertCount( 1, $columns );
		$this->assertEquals( 'POST COLUMN', reset( $columns ) );

		// to
		$mock = new P2P_Column_Mock( $ctype->set_direction( 'to' ) );

		$columns = $mock->add_column( array() );

		$this->assertCount( 1, $columns );
		$this->assertEquals( 'PAGE COLUMN', reset( $columns ) );
	}
}

