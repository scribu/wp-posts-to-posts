<?php

define( 'P2P_BOX_NONCE', 'p2p-box' );

class P2P_Box_Factory extends P2P_Factory {

	function __construct() {
		add_filter( 'p2p_connection_type_args', array( $this, 'filter_ctypes' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
		add_action( 'wp_ajax_p2p_box', array( $this, 'wp_ajax_p2p_box' ) );
	}

	function filter_ctypes( $args ) {
		if ( isset( $args['admin_box'] ) ) {
			$box_args = _p2p_pluck( $args, 'admin_box' );
			if ( !is_array( $box_args ) )
				$box_args = array( 'show' => $box_args );
		} else {
			$box_args = array();
		}

		foreach ( array( 'can_create_post' ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$box_args[ $key ] = _p2p_pluck( $args, $key );
			}
		}

		$box_args = wp_parse_args( $box_args, array(
			'show' => 'any',
			'context' => 'side',
			'priority' => 'default',
			'can_create_post' => true
		) );

		$this->register( $args['name'], $box_args );

		return $args;
	}

	function add_meta_boxes( $post_type ) {
		$this->filter( 'post', $post_type );
	}

	function add_item( $directed, $object_type, $post_type, $title ) {
		if ( !self::show_box( $directed, $GLOBALS['post'] ) )
			return;

		$box = $this->create_box( $directed );
		$box_args = $this->queue[ $directed->name ];

		add_meta_box(
			sprintf( 'p2p-%s-%s', $directed->get_direction(), $directed->name ),
			$title,
			array( $box, 'render' ),
			$post_type,
			$box_args->context,
			$box_args->priority
		);

		$box->init_scripts();
	}

	private static function show_box( $directed, $post ) {
		$show = $directed->get_opposite( 'side' )->can_edit_connections();

		return apply_filters( 'p2p_admin_box_show', $show, $directed, $post );
	}

	private function create_box( $directed ) {
		$box_args = $this->queue[ $directed->name ];

		$title_class = 'P2P_Field_Title_' . ucfirst( $directed->get_opposite( 'object' ) );

		$columns = array(
			'delete' => new P2P_Field_Delete,
			'title' => new $title_class( $directed->get_opposite( 'labels' )->singular_name ),
		);

		foreach ( $directed->fields as $key => $data ) {
			$columns[ 'meta-' . $key ] = new P2P_Field_Generic( $key, $data );
		}

		if ( $orderby_key = $directed->get_orderby_key() ) {
			$columns['order'] = new P2P_Field_Order( $orderby_key );
		}

		return new P2P_Box( $box_args, $columns, $directed );
	}

	/**
	 * Collect metadata from all boxes.
	 */
	function save_post( $post_id, $post ) {
		if ( 'revision' == $post->post_type || defined( 'DOING_AJAX' ) )
			return;

		if ( isset( $_POST['p2p_connections'] ) ) {
			// Loop through the hidden fields instead of through $_POST['p2p_meta'] because empty checkboxes send no data.
			foreach ( $_POST['p2p_connections'] as $p2p_id ) {
				$data = stripslashes_deep( scbForms::get_value( array( 'p2p_meta', $p2p_id ), $_POST, array() ) );

				$connection = p2p_get_connection( $p2p_id );

				$fields = p2p_type( $connection->p2p_type )->fields;

				foreach ( $fields as $key => &$field ) {
					$field['name'] = $key;
				}

				$data = scbForms::validate_post_data( $fields, $data );

				scbForms::update_meta( $fields, self::addslashes_deep( $data ), $p2p_id, 'p2p' );
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

	private function addslashes_deep( $value ) {
		if ( is_array( $value ) )
			return array_map( array( __CLASS__, __METHOD__ ), $value );

		return addslashes( $value );
	}

	/**
	 * Controller for all box ajax requests.
	 */
	function wp_ajax_p2p_box() {
		check_ajax_referer( P2P_BOX_NONCE, 'nonce' );

		$ctype = p2p_type( $_REQUEST['p2p_type'] );
		if ( !$ctype || !isset( $this->queue[$ctype->name] ) )
			die(0);

		$directed = $ctype->set_direction( $_REQUEST['direction'] );
		if ( !$directed )
			die(0);

		$post = get_post( $_REQUEST['from'] );
		if ( !$post )
			die(0);

		if ( !self::show_box( $directed, $post ) )
			die(-1);

		$box = $this->create_box( $directed );

		$method = 'ajax_' . $_REQUEST['subaction'];

		$box->$method();
	}
}

new P2P_Box_Factory;

