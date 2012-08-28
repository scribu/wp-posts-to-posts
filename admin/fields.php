<?php

class P2P_Field_Delete implements P2P_Field {

	function get_title() {
		$data = array(
			'title' => __( 'Delete all connections', P2P_TEXTDOMAIN )
		);

		return P2P_Mustache::render( 'column-delete-all', $data );
	}

	function render( $p2p_id, $_ ) {
		$data = array(
			'p2p_id' => $p2p_id,
			'title' => __( 'Delete connection', P2P_TEXTDOMAIN )
		);

		return P2P_Mustache::render( 'column-delete', $data );
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

	function render( $p2p_id, $_ ) {
		return html( 'input', array(
			'type' => 'hidden',
			'name' => "p2p_order[$this->sort_key][]",
			'value' => $p2p_id
		) );
	}
}


class P2P_Field_Generic implements P2P_Field {

	protected $key;
	protected $data;

	function __construct( $key, $data ) {
		$this->key = $key;
		$this->data = $data;
	}

	function get_title() {
		return $this->data['title'];
	}

	function render( $p2p_id, $_ ) {
		$args = $this->data;
		$args['name'] = array( 'p2p_meta', $p2p_id, $this->key );

		if ( 'select' == $args['type'] && !isset( $args['text'] ) )
			$args['text'] = '';

		return scbForms::input_from_meta( $args, $p2p_id, 'p2p' );
	}
}


class P2P_Field_Create implements P2P_Field {

	protected $title_field;

	function __construct( $title_field ) {
		$this->title_field = $title_field;
	}

	function get_title() {
		// Not needed
		return '';
	}

	function render( $p2p_id, $item ) {
		$data = array_merge( $this->title_field->get_data( $item ), array(
			'title' => $item->get_title(),
			'item-id' => $item->get_id(),
		) );

		return P2P_Mustache::render( 'column-create', $data );
	}
}


abstract class P2P_Field_Title implements P2P_Field {

	protected $title;

	function __construct( $title = '' ) {
		$this->title = $title;
	}

	function get_title() {
		return $this->title;
	}

	function render( $p2p_id, $item ) {
		$data = array_merge( $this->get_data( $item ), array(
			'title' => $item->get_title(),
			'url' => $item->get_editlink(),
		) );

		return P2P_Mustache::render( 'column-title', $data );
	}

	abstract function get_data( $item );
}

class P2P_Field_Title_Post extends P2P_Field_Title {

	function get_data( $item ) {
		$data = array(
			'title-attr' => $item->get_permalink()
		);

		$post = $item->get_object();

		if ( 'publish' != $post->post_status ) {
			$status_obj = get_post_status_object( $post->post_status );
			if ( $status_obj ) {
				$data['status']['text'] = $status_obj->label;
			}
		}

		return $data;
	}
}

class P2P_Field_Title_Attachment extends P2P_Field_Title {

	function get_data( $item ) {
		$data = array(
			'title-attr' => $item->get_object()->post_title,
		);

		return $data;
	}
}

class P2P_Field_Title_User extends P2P_Field_Title {

	function get_data( $user ) {
		return array(
			'title-attr' => '',
		);
	}
}

