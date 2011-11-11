<?php

define( 'P2P_BOX_NONCE', 'p2p-box' );

/**
 * @package Administration
 */
class P2P_Box_Factory {

	function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'wp_ajax_p2p_box', array( __CLASS__, 'wp_ajax_p2p_box' ) );
	}

	/**
	 * Add all the metaboxes.
	 */
	static function add_meta_boxes( $post_type ) {
		foreach ( P2P_Connection_Type::get() as $ctype_id => $ctype ) {
			$directed = $ctype->find_direction( $post_type );
			if ( !$directed )
				continue;

			if ( !isset( $ctype->_metabox_args ) )
				continue;

			$metabox_args = $ctype->_metabox_args;

			if ( $ctype->indeterminate && !$ctype->reciprocal ) {
				if ( 'any' == $metabox_args->show_ui ) {
					$dir = array( 'from', 'to' );
					$two_boxes = true;
				} else {
					$dir = array( $metabox_args->show_ui );
					$two_boxes = false;
				}

				foreach ( $dir as $direction ) {
					$directed = $ctype->set_direction( $direction );
					if ( !$directed )
						continue;

					$box = new P2P_Box( $metabox_args, $directed, $post_type );
					$box->register( $two_boxes );
				}
			} elseif ( 'any' == $metabox_args->show_ui || $directed->get_direction() == $metabox_args->show_ui ) {
				$box = new P2P_Box( $metabox_args, $directed, $post_type );
				$box->register();
			}
		}
	}

	/**
	 * Collect metadata from all boxes.
	 */
	function save_post( $post_id, $post ) {
		if ( 'revision' == $post->post_type || defined( 'DOING_AJAX' ) )
			return;

		// Custom fields
		if ( isset( $_POST['p2p_meta'] ) ) {
			foreach ( $_POST['p2p_meta'] as $ctype_id => $data ) {
				$ctype = p2p_type( $ctype_id );
				if ( !$ctype )
					continue;

				foreach ( $data as $p2p_id => $data ) {
					foreach ( $ctype->_metabox_args->fields as $key => $field_args ) {
						if ( 'checkbox' == $field_args['type'] ) {
							if ( isset( $data[$key] ) )
								$new_values = $data[$key];
							else
								$new_values = array();

							$old_values = p2p_get_meta( $p2p_id, $key );

							foreach ( array_diff( $new_values, $old_values ) as $value )
								p2p_add_meta( $p2p_id, $key, $value );

							foreach ( array_diff( $old_values, $new_values ) as $value )
								p2p_delete_meta( $p2p_id, $key, $value );
						} else {
							p2p_update_meta( $p2p_id, $key, $data[$key] );
						}
					}
				}
			}
		}

		// Ordering
		if ( isset( $_POST['p2p_order'] ) ) {
			foreach ( $_POST['p2p_order'] as $key => $list ) {
				foreach ( $list as $i => $p2p_id ) {
					p2p_update_meta( $p2p_id, $key, $i );
				}
			}
		}
	}

	/**
	 * Controller for all box ajax requests.
	 */
	function wp_ajax_p2p_box() {
		check_ajax_referer( P2P_BOX_NONCE, 'nonce' );

		$ctype = p2p_type( $_REQUEST['ctype_id'] );
		if ( !$ctype || !isset( $ctype->_metabox_args ) )
			die(0);

		$post_type = get_post_type( $_REQUEST['from'] );
		if ( !$post_type )
			die(0);

		$directed = $ctype->set_direction( $_REQUEST['direction'] );
		if ( !$directed )
			die(0);

		$box = new P2P_Box( $ctype->_metabox_args, $directed, $post_type );

		if ( !current_user_can( $box->ptype->cap->edit_posts ) )
			die(-1);

		$method = 'ajax_' . $_REQUEST['subaction'];

		$box->$method();
	}
}

P2P_Box_Factory::init();

