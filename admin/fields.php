<?php

class P2P_Field_Create implements P2P_Field {

	function get_title() {
		// Not needed
		return '';
	}

	function render( $key, $p2p_id, $post_id ) {
		$data = array(
			'post_id' => $post_id,
			'title' => __( 'Create connection', P2P_TEXTDOMAIN )
		);

		return _p2p_mustache_render( 'column-create.html', $data );
	}
}


class P2P_Field_Delete implements P2P_Field {

	function get_title() {
		$data = array(
			'title' => __( 'Delete all connections', P2P_TEXTDOMAIN )
		);

		return _p2p_mustache_render( 'column-delete-all.html', $data );
	}

	function render( $key, $p2p_id, $post_id ) {
		$data = array(
			'p2p_id' => $p2p_id,
			'title' => __( 'Delete connection', P2P_TEXTDOMAIN )
		);

		return _p2p_mustache_render( 'column-delete.html', $data );
	}
}


class P2P_Field_Order implements P2P_Field {

	protected $sort_key;

	function __construct( $sort_key ) {
		$this->sort_key = $sort_key;
	}

	function get_title() {
		return '';
	}

	function render( $key, $p2p_id, $post_id ) {
		return html( 'input', array(
			'type' => 'hidden',
			'name' => "p2p_order[$this->sort_key][]",
			'value' => $p2p_id
		) );
	}
}


class P2P_Field_Title implements P2P_Field {

	protected $title;

	function __construct( $title = '' ) {
		$this->title = $title;
	}

	function get_title() {
		return $this->title;
	}

	function render( $key, $p2p_id, $post_id ) {
		$data = array(
			'title-attr' => get_post_type_object( get_post_type( $post_id ) )->labels->edit_item,
			'title' => get_post_field( 'post_title', $post_id ),
			'url' => get_edit_post_link( $post_id ),
		);

		$post_status = get_post_status( $post_id );

		if ( 'publish' != $post_status ) {
			$status_obj = get_post_status_object( $post_status );
			if ( $status_obj ) {
				$data['status']['text'] = $status_obj->label;
			}
		}

		return _p2p_mustache_render( 'column-title.html', $data );
	}
}


class P2P_Field_Generic implements P2P_Field {

	protected $data;

	function __construct( $data ) {
		if ( !is_array( $data ) )
			$data = array( 'title' => $data );

		$this->data = $data;
	}

	function get_title() {
		return $this->data['title'];
	}

	function render( $key, $p2p_id, $post_id ) {
		$form = new scbForm(
			array( $key => p2p_get_meta( $p2p_id, $key, true ) ),
			array( 'p2p_meta', $p2p_id )
		);

		$args = array(
			'type' => 'text',
			'name' => $key
		);

		if ( isset( $this->data['values'] ) ) {
			$args['type'] = 'select';
			$args['value'] = $this->data['values'];
		}

		return $form->input( $args );
	}
}

