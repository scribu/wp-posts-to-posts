<?php

class P2P_Tests_List extends WP_UnitTestCase {

	static $list;

	static function setUpBeforeClass() {
		$list = array();

		for ( $i = 0; $i < 3; $i++ ) {
			$list[] = array(
				'id' => $i
			);
		}

		self::$list = new P2P_List( $list, 'P2P_Item_Any' );
	}

	private static function count_occurences( $haystack, $needle ) {
		$count = 0;
		$offset = 0;

		$n = strlen( $haystack );

		while ( $offset < $n ) {
			$offset = strpos( $haystack, $needle, $offset );

			if ( false === $offset )
				break;

			$offset++;

			$count++;
		}

		return $count;
	}

	function test_default_output() {
		$output = self::$list->render( array(
			'echo' => false
		) );

		$this->assertStringStartsWith( '<ul>', $output );
		$this->assertStringEndsWith( '</ul>', $output );
	}

	function test_separator_output() {
		$output = self::$list->render( array(
			'separator' => __FUNCTION__,
			'echo' => false
		) );

		$this->assertNotEmpty( $output );

		// 'separator' should appear n-1 times in the output
		$this->assertEquals(
			count( self::$list->items ) - 1,
			self::count_occurences( $output, __FUNCTION__ )
		);

		// 'before_list' and 'after_list' should be ignored
		$this->assertStringStartsNotWith( '<ul>', $output );
		$this->assertStringEndsNotWith( '</ul>', $output );
	}
}

