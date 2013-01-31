<?php

class P2P_Indeterminate_Connection_Type extends P2P_Connection_Type {

	protected $directed_class = 'P2P_Indeterminate_Directed_Connection_Type';

	protected $arrow = '&harr;';

	protected function choose_direction( $direction ) {
		return 'from';
	}

	function _directions_for_admin( $direction, $show_ui ) {
		return parent::_directions_for_admin( 'any', $show_ui );
	}
}

