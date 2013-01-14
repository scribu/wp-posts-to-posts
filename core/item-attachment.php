<?php

class P2P_Item_Attachment extends P2P_Item_Post {

	function get_title() {
		if( wp_attachment_is_image( $this->item->ID ) )
			return wp_get_attachment_image( $this->item->ID, 'thumbnail', false );

		return get_the_title( $this->item );
	}
}

