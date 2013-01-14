<?php

class P2P_Field_Title_Attachment extends P2P_Field_Title {

	function get_data( $item ) {
		$data = array(
			'title-attr' => $item->get_object()->post_title,
		);

		return $data;
	}
}

