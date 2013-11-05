<?php

/**
 * A P2P admin metabox is composed of several "fields".
 */
interface P2P_Field {
	function get_title();
	function render( $p2p_id, $item );
}

