<?php

class P2P_Field_Title_Bpgroup extends P2P_Field_Title {

	function get_data( $item ) {
		$data = array(
			'title-attr' => $item->get_permalink()
		);

		$group = $item->get_object();

		if ( $group ) {
			$data['status']['text'] = __(ucwords($group->status),'clariner');
		}

		return $data;
	}
}

