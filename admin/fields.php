<?php

class P2P_Field_Delete implements P2P_Field {

	function get_title() {
		$data = array(
			'title' => __( 'Delete all connections', P2P_TEXTDOMAIN )
		);

		return P2P_Mustache::render( 'column-delete-all', $data );
	}

	function render( $p2p_id, $item ) {
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

	function render( $p2p_id, $item ) {
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

	function render( $p2p_id, $item ) {
		$args = array(
			'name' => array( 'p2p_meta', $p2p_id, $this->key ),
			'type' => $this->data['type']
		);

		if ( isset( $this->data['values'] ) )
			$args['values'] = $this->data['values'];

		if ( 'select' == $args['type'] )
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
			'item-id' => $item->ID, // TODO: use P2P_Side->item_id()
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
		return P2P_Mustache::render( 'column-title', $this->get_data( $item ) );
	}

	abstract function get_data( $item );
}

class P2P_Field_Title_Post extends P2P_Field_Title {

	function get_data( $post ) {
		$data = array(
			'title-attr' => get_permalink( $post ),
			'title' => $post->post_title,
			'url' => get_edit_post_link( $post ),
		);

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

	function get_data( $attachment ) {
		list( $src ) = wp_get_attachment_image_src( $attachment->ID, 'thumbnail', true );

		$data = array(
			'title-attr' => $attachment->post_title,
			'title' => html( 'img', compact( 'src' ) ),
			'url' => get_edit_post_link( $attachment ),
		);

		return $data;
	}
}

class P2P_Field_Title_User extends P2P_Field_Title {

	function get_data( $user ) {
		return array(
			'title-attr' => '',
			'title' => $user->display_name,
			'url' => $this->get_edit_url( $user->ID ),
		);
	}

	private function get_edit_url( $user_id ) {
		if ( get_current_user_id() == $user_id ) {
			$edit_link = 'profile.php';
		} else {
			$edit_link = "user-edit.php?user_id=$user_id";
		}

		return admin_url( $edit_link );
	}
}

