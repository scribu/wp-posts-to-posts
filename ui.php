<?php

abstract class P2P_Box {
	public $from;
	public $to;

	protected $reversed;
	protected $direction;

	protected $box_id;

	abstract function box( $post_id );

	function setup() {}


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

	function _register( $from ) {
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

		add_meta_box(
			'p2p-connections-' . $this->box_id,
			$title,
			array( $this, '_box' ),
			$from,
			$this->context,
			'default'
		);
	}

	function _box( $post ) {
		$this->box( $post->ID );
	}
}


class P2P_Connection_Types {

	private static $ctypes = array();

	static public function register( $args ) {
		self::$ctypes[] = $args;
	}

	static function add_meta_boxes( $from ) {
		$filtered = self::filter_ctypes( $from );

		if ( empty( $filtered ) )
			return;

		foreach ( $filtered as $ctype ) {
			$ctype->_register( $from );
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

	function wp_ajax_p2p_connections() {
		$box = self::ajax_make_box();

		$ptype_obj = get_post_type_object( $box->from );
		if ( !current_user_can( $ptype_obj->cap->edit_posts ) )
			die(-1);

		$subaction = $_POST['subaction'];

		$box->$subaction();
	}

	function ajax_disconnect() {
		$box = self::ajax_make_box();

		$box->disconnect();
	}

	function wp_ajax_p2p_search() {
		$box = self::ajax_make_box();

		$rows = $box->handle_search( $_GET['post_id'], $_GET['paged'], $_GET['s'] );

		if ( $rows ) {
			$results = compact( 'rows' );
		} else {
			$results = array(
				'msg' => get_post_type_object( $box->to )->labels->not_found,
			);
		}

		die( json_encode( $results ) );
	}

	private static function ajax_make_box() {
		$box_id = absint( $_REQUEST['box_id'] );
		$direction = $_REQUEST['direction'];

		if ( !isset( self::$ctypes[ $box_id ] ) )
			die(0);

		$args = self::$ctypes[ $box_id ];

		return new $args['box']($args, $direction, $box_id);
	}

	private static function filter_ctypes( $post_type ) {
		$r = array();

		foreach ( self::$ctypes as $box_id => $args ) {
			$direction = false;

			if ( $args['reciprocal'] && $post_type == $args['from'] && $args['from'] == $args['to'] ) {
				$direction = 'any';
			} elseif ( $args['reciprocal'] && $post_type == $args['to'] ) {
				$direction = 'to';
			} elseif ( $post_type == $args['from'] ) {
				$direction = 'from';
			} else {
				continue;
			}

			if ( !$direction )
				continue;

			$r[ $box_id ] = new $args['box']($args, $direction, $box_id);
		}

		return $r;
	}
}
scbHooks::add( 'P2P_Connection_Types' );

