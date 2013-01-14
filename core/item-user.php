<?php

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

