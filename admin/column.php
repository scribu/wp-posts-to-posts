<?php

class P2P_Column {

	protected $ctype;

	protected $connected = array();

	function __construct( $directed ) {
		$this->ctype = $directed;

		$this->ctype->lose_direction()->each_connected( $GLOBALS['wp_query'] );

		$this->connected = scb_list_fold( $GLOBALS['wp_query']->posts, 'ID', 'connected' );
	}

	function add_column( $columns ) {
		$columns['connected'] = $this->ctype->get_title();

		return $columns;
	}

	function display_column( $column, $post_id ) {
		if ( 'connected' != $column )
			return;

		foreach ( $this->connected[ $post_id ] as $post ) {
			$args = array(
				'post_type' => get_post_type( $post_id ),
				'connected_type' => $this->ctype->id,
				'connected' => $post->ID,
			);

			$url = add_query_arg( $args, admin_url( 'edit.php' ) );

			echo html( 'li', html_link( $url, $post->post_title ) );
		}
	}
}

