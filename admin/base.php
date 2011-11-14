<?php

define( 'P2P_BOX_NONCE', 'p2p-box' );

/**
 * @package Administration
 */
class P2P_Box_Factory {

	static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'wp_ajax_p2p_box', array( __CLASS__, 'wp_ajax_p2p_box' ) );
	}

	static function add_meta_boxes( $post_type ) {
		foreach ( P2P_Connection_Type::get_all_instances() as $ctype_id => $ctype ) {
			if ( !isset( $ctype->_metabox_args ) )
				continue;

			$metabox_args = $ctype->_metabox_args;

			$dir = self::get_visible_directions( $post_type, $ctype, $metabox_args->show_ui );

			$title = $ctype->title;

			if ( count( $dir ) > 1 && $title['from'] == $title['to'] ) {
				$title['from'] .= __( ' (from)', P2P_TEXTDOMAIN );
				$title['to']   .= __( ' (to)', P2P_TEXTDOMAIN );
			}

			foreach ( $dir as $direction ) {
				$key = ( 'to' == $direction ) ? 'to' : 'from';

				$directed = $ctype->set_direction( $direction );

				$box = new P2P_Box( $metabox_args, $directed );

				if ( !$box->check_capability() )
					continue;

				add_meta_box(
					"p2p-{$direction}-{$ctype->id}",
					$title[$key],
					array( $box, 'render' ),
					$post_type,
					$metabox_args->context,
					'default'
				);

				$box->init_scripts();
			}
		}
	}

	private static function get_visible_directions( $post_type, $ctype, $show_ui ) {
		$direction = $ctype->find_direction( $post_type, false );
		if ( !$direction )
			return array();

		if ( $ctype->indeterminate && !$ctype->reciprocal ) {
			if ( 'any' == $show_ui )
				return array( 'from', 'to' );
			else
				return array( $show_ui );
		}

		if ( 'any' == $show_ui || $direction == $show_ui )
			return array( $direction );

		return array();
	}

	/**
	 * Collect metadata from all boxes.
	 */
	static function save_post( $post_id, $post ) {
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
	static function wp_ajax_p2p_box() {
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

		if ( !$box->check_capability() )
			die(-1);

		$method = 'ajax_' . $_REQUEST['subaction'];

		$box->$method();
	}
}

P2P_Box_Factory::init();

