<?php

/**
 * Pass through version to use when Composer handles classes load.
 *
 * @param callable $callback
 */
function scb_init( $callback = null ) {
	if ( $callback ) {
		call_user_func( $callback );
	}
}
