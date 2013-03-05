<?php

interface P2P_Direction_Strategy {
	function get_arrow();
	function choose_direction( $direction );
	function directions_for_admin( $direction, $show_ui );
	function get_directed_class();
}

