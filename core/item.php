<?php

abstract class P2P_Item {

	protected $item;

	function __construct( $item ) {
		$this->item = $item;
	}

	function __isset( $key ) {
		return isset( $this->item->$key );
	}

	function __get( $key ) {
		return $this->item->$key;
	}

	function __set( $key, $value ) {
		$this->item->$key = $value;
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
		return get_edit_user_link( $this->item->ID );
	}
}


// WP < 3.5
if ( !function_exists( 'get_edit_user_link' ) ) :
function get_edit_user_link( $user_id = null ) {
	if ( ! $user_id )
		$user_id = get_current_user_id();

	if ( empty( $user_id ) || ! current_user_can( 'edit_user', $user_id ) )
		return '';

	$user = new WP_User( $user_id );

	if ( ! $user->exists() )
		return '';

	if ( get_current_user_id() == $user->ID )
		$link = get_edit_profile_url( $user->ID );
	else
		$link = add_query_arg( 'user_id', $user->ID, self_admin_url( 'user-edit.php' ) );

	return apply_filters( 'get_edit_user_link', $link, $user->ID );
}
endif;

