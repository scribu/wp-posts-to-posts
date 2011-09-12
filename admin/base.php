<?php

define( 'P2P_BOX_NONCE', 'p2p-box' );


interface P2P_Box_UI {
	function get_title();
	function render( $post );
}


class P2P_Connection_Types {

	private static $ctypes = array();

	static public function register( $args ) {
		self::$ctypes[] = $args;
	}

	function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'wp_ajax_p2p_box', array( __CLASS__, 'wp_ajax_p2p_box' ) );
	}

	/**
	 * Add all the metaboxes.
	 */
	static function add_meta_boxes( $from ) {
		foreach ( self::$ctypes as $box_id => $args ) {
			$box = self::make_box( $box_id, $from );
			if ( !$box )
				continue;

			add_meta_box(
				'p2p-connections-' . $box->data->box_id,
				$box->get_title(),
				array( $box, 'render' ),
				$box->data->from,
				$box->data->context,
				'default'
			);
		}
	}

	/**
	 * Collect metadata from all boxes.
	 */
	function save_post( $post_id, $post ) {
		if ( 'revision' == $post->post_type || !isset( $_POST['p2p_meta'] ) )
			return;

		foreach ( $_POST['p2p_meta'] as $p2p_id => $data ) {
			foreach ( $data as $key => $value ) {
				p2p_update_meta( $p2p_id, $key, $value );
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
		if ( !isset( self::$ctypes[ $box_id ] ) )
			return false;

		$args = self::$ctypes[ $box_id ];

		$direction = self::get_direction( $post_type, $args );
		if ( !$direction )
			return false;

		$box_data = new P2P_Box_Data( $args, $direction, $box_id );

		return new $args['box']( $box_data );
	}

	private static function get_direction( $post_type, $args ) {
		$direction = false;

		if ( $args['reciprocal'] && $post_type == $args['from'] && $args['from'] == $args['to'] ) {
			$direction = 'any';
		} elseif ( $args['reciprocal'] && $post_type == $args['to'] ) {
			$direction = 'to';
		} elseif ( $post_type == $args['from'] ) {
			$direction = 'from';
		}

		return $direction;
	}
}

P2P_Connection_Types::init();

