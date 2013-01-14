<?php

class P2P_Column_User extends P2P_Column {

	function __construct( $directed ) {
		parent::__construct( $directed );

		add_action( 'pre_user_query', array( __CLASS__, 'user_query' ), 9 );

		add_filter( 'manage_users_custom_column', array( $this, 'display_column' ), 10, 3 );
	}

	protected function get_items() {
		global $wp_list_table;

		return $wp_list_table->items;
	}

	// Add the query vars to the global user query (on the user admin screen)
	static function user_query( $query ) {
		if ( isset( $query->_p2p_capture ) )
			return;

		// Don't overwrite existing P2P query
		if ( isset( $query->query_vars['connected_type'] ) )
			return;

		_p2p_append( $query->query_vars, wp_array_slice_assoc( $_GET,
			P2P_URL_Query::get_custom_qv() ) );
	}

	function get_admin_link( $item ) {
		$args = array(
			'connected_type' => $this->ctype->name,
			'connected_direction' => $this->ctype->flip_direction()->get_direction(),
			'connected_items' => $item->get_id(),
		);

		return add_query_arg( $args, admin_url( 'users.php' ) );
	}

	function display_column( $content, $column, $item_id ) {
		return parent::render_column( $column, $item_id );
	}
}

