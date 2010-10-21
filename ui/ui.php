<?php

abstract class P2P_Box {

	protected $reversed;
	protected $direction;

	protected $box_id;
	private $input;

	abstract function save( $post_id, $data );
	abstract function box( $post_id );

	protected function input_name( $name ) {
		return $this->input->get_name( $name );
	}


// Internal stuff


	public function __construct( $args, $reversed, $box_id ) {
		foreach ( $args as $key => $value )
			$this->$key = $value;

		$this->box_id = $box_id;
		$this->reversed = $reversed;

		$this->input = new p2pInput( array( 'p2p', $box_id ) );

		$this->direction = $this->reversed ? 'to' : 'from';

		if ( $this->reversed )
			list( $this->to, $this->from ) = array( $this->from, $this->to );
	}

	function _register( $from ) {
		$title = $this->title; 

		if ( empty( $title ) )
			$title = get_post_type_object( $this->to )->labels->name;

		add_meta_box(
			'p2p-connections-' . $this->box_id,
			$title,
			array( $this, '_box' ),
			$from,
			'side',
			'default'
		);
	}

	function _save( $post_id ) {
		$data = $this->input->extract( $_POST );

		if ( is_null( $data ) )
			return;

		$this->save( $post_id, $data );
	}

	function _box( $post ) {
		$this->box( $post->ID );
	}
}


class p2pInput {

	private $prefix;

	function __construct( $prefix = array() ) {
		$this->prefix = $prefix;
	}

	function get_name( $suffix ) {
		$name_a = array_merge( $this->prefix, (array) $suffix );
		
		$name = array_shift( $name_a );
		foreach ( $name_a as $key )
			$name .= '[' . esc_attr( $key ) . ']';

		return $name;
	}

	function extract( $value, $suffix = array() ) {
		$name_a = array_merge( $this->prefix, (array) $suffix );

		foreach ( $name_a as $key ) {
			if ( !isset( $value[ $key ] ) )
				return null;

			$value = $value[$key];
		}

		return $value;
	}
}


class P2P_Connection_Types {

	private static $ctypes = array();

	static public function register( $args ) {
		$args = wp_parse_args( $args, array(
			'from' => '',
			'to' => '',
			'box' => 'P2P_Box_Multiple',
			'title' => '',
			'reciprocal' => false
		) );

		self::$ctypes[] = $args;
	}

	static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, '_register' ) );
		add_action( 'save_post', array( __CLASS__, '_save' ), 10 );
		add_action( 'wp_ajax_p2p_search', array( __CLASS__, 'ajax_search' ) );
	}

	static function _register( $from ) {
		foreach ( self::filter_ctypes( $from ) as $ctype ) {
			$ctype->_register( $from );
		}
	}

	static function _save( $post_id ) {
		$from = get_post_type( $post_id );
		if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || empty( $_POST ) || 'revision' == $from )
			return;

		foreach ( self::filter_ctypes( $from ) as $ctype ) {
			$ctype->_save( $post_id );
		}
	}

	function ajax_search() {
		add_filter( 'posts_search', array( __CLASS__, '_search_by_title' ) );

		$box_id = absint( $_GET['box_id'] );
		$reversed = (bool) $_GET['reversed'];

		if ( !isset( self::$ctypes[ $box_id ] ) )
			die(0);

		$args = self::$ctypes[ $box_id ];
		$box = new $args['box']($args, $reversed, $box_id);

		$posts = get_posts( $box->get_search_args( $_GET['q'] ) );

		$results = array();
		foreach ( $posts as $post ) {
			$GLOBALS['post'] = $post;
			$results[ $post->ID ] = apply_filters( 'the_title', $post->post_title );
		}

		die( json_encode( $results ) );
	}

	function _search_by_title( $sql ) {
		remove_filter( current_filter(), array( __CLASS__, __FUNCTION__ ) );

		list( $sql ) = explode( ' OR ', $sql, 2 );

		return $sql . '))';
	}

	private static function filter_ctypes( $post_type ) {
		$r = array();
		foreach ( self::$ctypes as $box_id => $args ) {
			if ( $post_type == $args['from'] ) {
				$reversed = false;
			} elseif ( $args['reciprocal'] && $post_type == $args['to'] ) {
				$reversed = true;
			} else {
				continue;
			}

			$r[ $box_id ] = new $args['box']($args, $reversed, $box_id);
		}

		return $r;
	}
}

