<?php

abstract class P2P_Item {

	protected $item;

	function __construct( $item ) {
		$this->item = $item;
	}

	function __get( $key ) {
		return $this->item->$key;
	}

	function get_object() {
		return $this->item;
	}

	function get_id() {
		return $this->item->ID;
	}

	abstract function get_permalink();
	abstract function get_title();
}


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


class P2P_Item_Attachment extends P2P_Item_Post {

	function get_title() {
		return wp_get_attachment_image( $this->item->ID, 'thumbnail', false );
	}
}


class P2P_Item_User extends P2P_Item {

	function get_title() {
		return $this->item->display_name;
	}

	function get_permalink() {
		return get_author_posts_url( $this->item->ID );
	}

	function get_editlink() {
		$user_id = $this->item->ID;

		if ( get_current_user_id() == $user_id ) {
			$edit_link = 'profile.php';
		} else {
			$edit_link = "user-edit.php?user_id=$user_id";
		}

		return admin_url( $edit_link );
	}
}

