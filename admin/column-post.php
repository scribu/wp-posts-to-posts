<?php

class P2P_Column_Post extends P2P_Column {

	function __construct( $directed ) {
		parent::__construct( $directed );

		$screen = get_current_screen();

		add_action( "manage_{$screen->post_type}_posts_custom_column", array( $this, 'display_column' ), 10, 2 );
	}

	protected function get_items() {
		global $wp_query;

		return $wp_query->posts;
	}

	function get_admin_link( $item ) {
		$args = array(
			'connected_type' => $this->ctype->name,
			'connected_direction' => $this->ctype->flip_direction()->get_direction(),
			'connected_items' => $item->get_id(),
			'action' => 'edit'
		);

		if($item instanceof P2P_Item_User) {
			$args['user_id'] = $item->get_id();
			return add_query_arg($args, admin_url('user-edit.php'));
		} else {
			$args['post'] = $item->get_id();
			return add_query_arg( $args, admin_url( 'post.php' ) );
		}
	}

	function display_column( $column, $item_id ) {
		echo parent::render_column( $column, $item_id );
	}
}

