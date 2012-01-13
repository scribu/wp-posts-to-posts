<?php

define( 'P2P_BOX_NONCE', 'p2p-box' );

/**
 * @package Administration
 */
class P2P_Box_Factory {

	private static $box_args = array();

	static function register( $p2p_type, $box_args ) {
		if ( isset( self::$box_args[$p2p_type] ) )
			return false;

		$box_args = (object) wp_parse_args( $box_args, array(
			'show' => 'any',
			'context' => 'side',
			'fields' => array(),
			'can_create_post' => true
		) );

		if ( !$box_args->show )
			return false;

		foreach ( $box_args->fields as &$field_args ) {
			if ( !is_array( $field_args ) )
				$field_args = array( 'title' => $field_args );

			$field_args['type'] = _p2p_get_field_type( $field_args );

			if ( 'checkbox' == $field_args['type'] && !isset( $field_args['values'] ) )
				$field_args['values'] = array( true => ' ' );
		}

		self::$box_args[$p2p_type] = $box_args;

		return true;
	}

	static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'wp_ajax_p2p_box', array( __CLASS__, 'wp_ajax_p2p_box' ) );
	}

	static function add_meta_boxes( $post_type ) {
		foreach ( self::$box_args as $p2p_type => $box_args ) {
			$ctype = p2p_type( $p2p_type );

			$dir = self::get_visible_directions( $post_type, $ctype, $box_args->show );

			$title = $ctype->title;

			if ( count( $dir ) > 1 && $title['from'] == $title['to'] ) {
				$title['from'] .= __( ' (from)', P2P_TEXTDOMAIN );
				$title['to']   .= __( ' (to)', P2P_TEXTDOMAIN );
			}

			foreach ( $dir as $direction ) {
				$key = ( 'to' == $direction ) ? 'to' : 'from';

				$directed = $ctype->set_direction( $direction );

				$box = new P2P_Box( $box_args, $directed );

				if ( !$box->check_capability() )
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

	private static function get_visible_directions( $post_type, $ctype, $show_ui ) {
		$direction = $ctype->find_direction( $post_type, false );
		if ( !$direction )
			return array();

		if ( $ctype->indeterminate && !$ctype->reciprocal ) {
			return _p2p_expand_direction( $show_ui );
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
		if ( isset( $_POST['p2p_types'] ) ) {
			foreach ( $_POST['p2p_types'] as $p2p_type ) {
				$ctype = p2p_type( $p2p_type );
				if ( !$ctype )
					continue;

				foreach ( $ctype->get_connections( $post_id ) as $p2p_id => $item_id ) {
					$data = scbForms::get_value( array( 'p2p_meta', $p2p_id ), $_POST, array() );

					foreach ( self::$box_args[$p2p_type]->fields as $key => $field_args ) {
						if ( 'checkbox' == $field_args['type'] ) {
							$new_values = scbForms::get_value( $key, $data, array() );

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

		$ctype = p2p_type( $_REQUEST['p2p_type'] );
		if ( !$ctype || !isset( self::$box_args[$ctype->name] ) )
			die(0);

		$post_type = get_post_type( $_REQUEST['from'] );
		if ( !$post_type )
			die(0);

		$directed = $ctype->set_direction( $_REQUEST['direction'] );
		if ( !$directed )
			die(0);

		$box = new P2P_Box( self::$box_args[$ctype->name], $directed, $post_type );

		if ( !$box->check_capability() )
			die(-1);

		$method = 'ajax_' . $_REQUEST['subaction'];

		$box->$method();
	}
}

P2P_Box_Factory::init();

