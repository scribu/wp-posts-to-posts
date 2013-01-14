<?php

class P2P_Field_Delete implements P2P_Field {

	function get_title() {
		$data = array(
			'title' => __( 'Delete all connections', P2P_TEXTDOMAIN )
		);

		return P2P_Mustache::render( 'column-delete-all', $data );
	}

	function render( $p2p_id, $_ ) {
		$data = array(
			'p2p_id' => $p2p_id,
			'title' => __( 'Delete connection', P2P_TEXTDOMAIN )
		);

		return P2P_Mustache::render( 'column-delete', $data );
	}
}


