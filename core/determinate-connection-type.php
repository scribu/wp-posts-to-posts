<?php

class P2P_Determinate_Connection_Type implements P2P_Direction_Strategy {

	function get_arrow() {
		return '&rarr;';
	}

	function choose_direction( $direction ) {
		return $direction;
	}

	function directions_for_admin( $direction, $show_ui ) {
		return array_intersect(
			_p2p_expand_direction( $show_ui ),
			_p2p_expand_direction( $direction )
		);
	}

	function get_directed_class() {
		return 'P2P_Directed_Connection_Type';
	}
}

