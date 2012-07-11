<?php

define( 'P2P_BOX_NONCE', 'p2p-box' );

class P2P_Box_Factory {

	private static $box_args = array();

	static function init() {
		add_filter( 'p2p_connection_type_args', array( __CLASS__, 'filter_args' ) );

		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'wp_ajax_p2p_box', array( __CLASS__, 'wp_ajax_p2p_box' ) );
	}

	static function filter_args( $args ) {
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

		self::register( $args['name'], $box_args );

		return $args;
	}

	static function register( $p2p_type, $box_args ) {
		if ( isset( self::$box_args[$p2p_type] ) )
			return false;

		$box_args = (object) wp_parse_args( $box_args, array(
			'show' => 'any',
			'context' => 'side',
			'can_create_post' => true
		) );

		if ( !$box_args->show )
			return false;

		self::$box_args[$p2p_type] = $box_args;

		return true;
	}

	static function add_meta_boxes( $post_type ) {
		foreach ( self::$box_args as $p2p_type => $box_args ) {
			$ctype = p2p_type( $p2p_type );

			$directions = array_intersect(
				_p2p_expand_direction( $box_args->show ),
				$ctype->find_direction_from_post_type( $post_type )
			);

			$title = $ctype->title;

			if ( count( $directions ) > 1 && $title['from'] == $title['to'] ) {
				$title['from'] .= __( ' (from)', P2P_TEXTDOMAIN );
				$title['to']   .= __( ' (to)', P2P_TEXTDOMAIN );
			}

			foreach ( $directions as $direction ) {
				$key = ( 'to' == $direction ) ? 'to' : 'from';

				$directed = $ctype->set_direction( $direction );

				$box = self::create_box( $box_args, $directed );

				if ( !$box->can_edit_connections() )
					continue;

				add_meta_box(
					"p2p-{$direction}-{$ctype->name}",
					$title[$key],
					array( $box, 'render' ),
					$post_type,
					$box_args->context,
					'default'
				);

				$box->init_scripts();
			}
		}
	}

	private static function create_box( $box_args, $directed ) {
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
	static function save_post( $post_id, $post ) {
		if ( 'revision' == $post->post_type || defined( 'DOING_AJAX' ) )
			return;

		if ( isset( $_POST['p2p_connections'] ) ) {
			// Loop through the hidden fields instead of through $_POST['p2p_meta'] because empty checkboxes send no data.
			foreach ( $_POST['p2p_connections'] as $p2p_id ) {
				$data = scbForms::get_value( array( 'p2p_meta', $p2p_id ), $_POST, array() );

				$connection = p2p_get_connection( $p2p_id );

				$fields = p2p_type( $connection->p2p_type )->fields;

				foreach ( $fields as $key => &$field ) {
					$field['name'] = $key;
				}

				$data = scbForms::validate_post_data( $fields, $data );

				scbForms::update_meta( $fields, $data, $p2p_id, 'p2p' );
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

		$ctype = p2p_type( $_REQUEST['p2p_type'] );
		if ( !$ctype || !isset( self::$box_args[$ctype->name] ) )
			die(0);

		$post_type = get_post_type( $_REQUEST['from'] );
		if ( !$post_type )
			die(0);

		$directed = $ctype->set_direction( $_REQUEST['direction'] );
		if ( !$directed )
			die(0);

		$box = self::create_box( self::$box_args[$ctype->name], $directed );

		if ( !$box->can_edit_connections() )
			die(-1);

		$method = 'ajax_' . $_REQUEST['subaction'];

		$box->$method();
	}
}

P2P_Box_Factory::init();

