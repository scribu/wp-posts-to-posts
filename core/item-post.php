<?php

class P2P_Item_Post extends P2P_Item {

	function get_title() {
		return get_the_title( $this->item );
	}

	function get_permalink() {
		return get_permalink( $this->item );
	}

	function get_editlink() {
		return get_edit_post_link( $this->item );
	}
}

