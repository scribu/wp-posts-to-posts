<?php
/**
 * Automatic filter binding.
 */
class scbHooks {
	private static $mangle_name;

	/**
	 * Adds.
	 *
	 * @param string $class
	 *
	 * @return void
	 */
	public static function add( $class ) {
		self::_do( 'add_filter', $class );
	}

	/**
	 * Removes.
	 *
	 * @param string $class
	 *
	 * @return void
	 */
	public static function remove( $class ) {
		self::_do( 'remove_filter', $class );
	}

	/**
	 * Prints debug.
	 *
	 * @param string $class
	 * @param string $mangle_name (optional)
	 *
	 * @return void
	 */
	public static function debug( $class, $mangle_name = false ) {
		self::$mangle_name = $mangle_name;

		echo "<pre>";
		self::_do( array( __CLASS__, '_print' ), $class );
		echo "</pre>";
	}

	/**
	 * Prints.
	 *
	 * @param string $tag
	 * @param array $callback
	 * @param int $prio
	 * @param int $argc
	 *
	 * @return void
	 */
	private static function _print( $tag, $callback, $prio, $argc ) {
		$static = ! is_object( $callback[0] );

		if ( self::$mangle_name ) {
			$class = $static ? '__CLASS__' : '$this';
		} else if ( $static ) {
			$class = "'" . $callback[0] . "'";
		} else {
			$class = '$' . get_class( $callback[0] );
		}

		$func = "array( $class, '$callback[1]' )";

		echo "add_filter( '$tag', $func";

		if ( $prio != 10 || $argc > 1 ) {
			echo ", $prio";

			if ( $argc > 1 ) {
				echo ", $argc";
			}
		}

		echo " );\n";
	}

	/**
	 * Processes.
	 *
	 * @param string $action
	 * @param string $class
	 *
	 * @return void
	 */
	private static function _do( $action, $class ) {
		$reflection = new ReflectionClass( $class );

		foreach ( $reflection->getMethods() as $method ) {
			if ( $method->isPublic() && ! $method->isConstructor() ) {
				$comment = $method->getDocComment();

				if ( preg_match( '/@nohook[ \t\*\n]+/', $comment ) ) {
					continue;
				}

				preg_match_all( '/@hook:?\s+([^\s]+)/', $comment, $matches ) ? $matches[1] : $method->name;
				if ( empty( $matches[1] ) ) {
					$hooks = array( $method->name );
				} else {
					$hooks = $matches[1];
				}

				$priority = preg_match( '/@priority:?\s+(\d+)/', $comment, $matches ) ? $matches[1] : 10;

				foreach ( $hooks as $hook ) {
					call_user_func( $action, $hook, array( $class, $method->name ), $priority, $method->getNumberOfParameters() );
				}
			}
		}

	}

}

