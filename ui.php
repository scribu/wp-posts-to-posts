<?php

define( 'P2P_BOX_NONCE', 'p2p-box' );

abstract class P2P_Box {
	public $box_id;

	public $from;
	public $to;

	protected $reversed;
	protected $direction;

	// Enqueue scripts here
	function setup() {}

	// This is where the box content goes
	abstract function render_box( $post_id );

	function get_box_title() {
		if ( is_array( $this->title ) ) {
			$key = $this->reversed ? 'to' : 'from';

			if ( isset( $this->title[ $key ] ) )
				$title = $this->title[ $key ];
			else
				$title = '';
		} else {
			$title = $this->title;
		}

		if ( empty( $title ) ) {
			$title = sprintf( __( 'Connected %s', 'posts-to-posts' ), get_post_type_object( $this->to )->labels->name );
		}

		return $title;
	}


// Internal stuff


	public function __construct( $args, $direction, $box_id ) {
		foreach ( $args as $key => $value )
			$this->$key = $value;

		$this->box_id = $box_id;

		$this->direction = $direction;

		$this->reversed = ( 'to' == $direction );

		if ( $this->reversed )
			list( $this->to, $this->from ) = array( $this->from, $this->to );

		$this->setup();
	}

	function _box( $post ) {
		$this->render_box( $post->ID );
	}
}


class P2P_Connection_Types {

	private static $ctypes = array();

	static public function register( $args ) {
		self::$ctypes[] = $args;
	}

	static function add_meta_boxes( $from ) {
		foreach ( self::$ctypes as $box_id => $args ) {
			$box = self::make_box( $box_id, $from );
			if ( !$box )
				continue;

			add_meta_box(
				'p2p-connections-' . $box->box_id,
				$box->get_box_title(),
				array( $box, '_box' ),
				$box->from,
				$box->context,
				'default'
			);
		}
	}

	function save_post( $post_id, $post ) {
		if ( 'revision' == $post->post_type || !isset( $_POST['p2p_meta'] ) )
			return;

		foreach ( $_POST['p2p_meta'] as $p2p_id => $data ) {
			foreach ( $data as $key => $value ) {
				p2p_update_meta( $p2p_id, $key, $value );
			}
		}
	}

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

		return new $args['box']( $args, $direction, $box_id );
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
scbHooks::add( 'P2P_Connection_Types' );

