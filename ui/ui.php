<?php

abstract class P2P_Box {
	protected $reversed;

	abstract function save( $post_id );
	abstract function box( $post_id );

	public function __construct( $args, $reversed, $box_id ) {
		foreach ( $args as $key => $value )
			$this->$key = $value;

		$this->box_id = $box_id;
		$this->reversed = $reversed;

		if ( $this->reversed )
			list( $this->to, $this->from ) = array( $this->from, $this->to );
	}

	function _box( $post ) {
		$this->box( $post->ID );
	}
}

class P2P_Connection_Types {
	private static $ctype_id = 0;
	private static $ctypes = array();

	static public function register( $args ) {
		$args = wp_parse_args( $args, array(
			'from' => '',
			'to' => '',
			'box' => 'P2P_Box_Multiple',
			'title' => '',
		) );

		self::$ctypes[] = $args;
	}

	static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, '_register' ) );
		add_action( 'save_post', array( __CLASS__, '_save' ), 10 );
	}

	static function _register( $from ) {
		foreach ( self::filter_ctypes( $from ) as $ctype ) {
			$title = $ctype->title; 

			if ( empty( $ctype->title ) )
				$title = get_post_type_object( $ctype->to )->labels->name;

			add_meta_box(
				'p2p-connections-' . $ctype->box_id,
				$title,
				array( $ctype, '_box' ),
				$from,
				'side',
				'default'
			);
		}
	}

	static function _save( $post_id ) {
		$from = get_post_type( $post_id );
		if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || empty( $_POST ) || 'revision' == $from )
			return;

		foreach ( self::filter_ctypes( $from ) as $ctype ) {
			$ctype->save($post_id);
		}
	}

	private static function filter_ctypes( $post_type ) {
		$r = array();
		$i = 0;
		foreach ( self::$ctypes as $args ) {
			if ( $post_type == $args['from'] ) {
				$reversed = false;
			} elseif ( $args['reciprocal'] && $post_type == $args['to'] ) {
				$reversed = true;
			} else {
				continue;
			}

			$r[] = new $args['box']($args, $reversed, $i++);
		}

		return $r;
	}
}

