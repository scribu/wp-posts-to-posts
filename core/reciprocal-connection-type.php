<?php

class P2P_Reciprocal_Connection_Type extends P2P_Indeterminate_Connection_Type {

	protected function choose_direction( $direction ) {
		return 'any';
	}

	function _directions_for_admin( $direction, $show_ui ) {
		if ( $show_ui )
			$directions = array( 'any' );
		else
			$directions = array();

		return $directions;
	}
}

