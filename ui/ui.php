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

	static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, '_register' ) );

		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'wp_ajax_p2p_search', array( __CLASS__, 'ajax_search' ) );
		add_action( 'wp_ajax_p2p_connections', array( __CLASS__, 'ajax_connections' ) );
	}

	static function _register( $from ) {
		$filtered = self::filter_ctypes( $from );

		if ( empty( $filtered ) )
			return;

		foreach ( $filtered as $ctype ) {
			$ctype->_register( $from );
		}

		wp_enqueue_style( 'p2p-admin', plugins_url( 'ui.css', __FILE__ ), array(), '0.7-beta' );
		wp_enqueue_script( 'p2p-admin', plugins_url( 'ui.js', __FILE__ ), array( 'jquery' ), '0.7-beta', true );
		wp_localize_script( 'p2p-admin', 'P2PAdmin_I18n', array(
			'deleteConfirmMessage' => __( 'Are you sure you want to delete all connections?', 'posts-to-posts' ),
		) );
	}

	function save( $post_id, $post ) {
		if ( 'revision' == $post->post_type || !isset( $_POST['p2p_meta'] ) )
			return;

		foreach ( $_POST['p2p_meta'] as $p2p_id => $data ) {
			foreach ( $data as $key => $value ) {
				p2p_update_meta( $p2p_id, $key, $value );
			}
		}
	}

	function ajax_connections() {
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

	function ajax_search() {
		add_filter( 'posts_search', array( __CLASS__, '_search_by_title' ), 10, 2 );

		$box = self::ajax_make_box();

		$args = array(
			's' => $_GET['s'],
			'paged' => $_GET['paged']
		);

		$query = new WP_Query( $box->get_search_args( $args, $_GET['post_id'] ) );

		if ( !$query->have_posts() ) {
			$results = array(
				'msg' => get_post_type_object( $box->to )->labels->not_found,
			);
		} else {
			ob_start();
			foreach ( $query->posts as $post ) {
				$box->results_row( $post );
			}

			$results = array(
				'rows' => ob_get_clean(),
				'pages' => $query->max_num_pages
			);
		}

		echo json_encode( $results );

		die;
	}

	function _search_by_title( $sql, $wp_query ) {
		remove_filter( current_filter(), array( __CLASS__, __FUNCTION__ ) );
		
		if ( $wp_query->is_search ) {
			list( $sql ) = explode( ' OR ', $sql, 2 );
			return $sql . '))';
		}
		
		return $sql;
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

