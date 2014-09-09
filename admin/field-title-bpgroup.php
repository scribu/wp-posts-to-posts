<?php

class P2P_Field_Title_Bpgroup extends P2P_Field_Title {

	function get_data( $item ) {
		$data = array(
			'title-attr' => $item->get_permalink()
		);

		$data['status']['text'] = __(ucwords($item->status),'clariner');

		return $data;
	}
}

