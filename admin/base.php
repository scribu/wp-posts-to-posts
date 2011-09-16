<?php

define( 'P2P_BOX_NONCE', 'p2p-box' );

class P2P_Connection_Types {

	function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'wp_ajax_p2p_box', array( __CLASS__, 'wp_ajax_p2p_box' ) );
	}

	/**
	 * Add all the metaboxes.
	 */
	static function add_meta_boxes( $from ) {
		foreach ( $GLOBALS['_p2p_connection_types'] as $box_id => $args ) {
			$box = self::make_box( $box_id, $from );
			if ( !$box )
				continue;

			$box->register();
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
			foreach ( $_POST['p2p_meta'] as $p2p_id => $data ) {
				foreach ( $data as $key => $value ) {
					p2p_update_meta( $p2p_id, $key, $value );
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

		$box = self::make_box( $_REQUEST['box_id'], $_REQUEST['post_type'] );
		if ( !$box )
			die(0);

		$ptype_obj = get_post_type_object( $box->from );
		if ( !current_user_can( $ptype_obj->cap->edit_posts ) )
			die(-1);

		$method = 'ajax_' . $_REQUEST['subaction'];

		$box->$method();
	}

	private static function make_box( $box_id, $post_type ) {
		if ( !isset( $GLOBALS['_p2p_connection_types'][ $box_id ] ) )
			return false;

		$args = $GLOBALS['_p2p_connection_types'][ $box_id ];

		$reciprocal = _p2p_pluck( $args, 'reciprocal' );

		$direction = false;

		if ( $reciprocal && $post_type == $args['from'] && $args['from'] == $args['to'] ) {
			$direction = 'any';
		} elseif ( $reciprocal && $post_type == $args['to'] ) {
			$direction = 'to';
		} elseif ( $post_type == $args['from'] ) {
			$direction = 'from';
		}

		if ( !$direction )
			return false;

		$args['direction'] = $direction;

		$metabox_args = array();
		foreach ( array( 'context' ) as $key ) {
			$metabox_args[ $key ] = _p2p_pluck( $args, $key );
		}

		$policy = new P2P_Connections_Policy( $args );

		return new P2P_Box( $box_id, $policy, $metabox_args );
	}
}

P2P_Connection_Types::init();

