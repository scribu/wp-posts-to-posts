<?php

interface P2P_Field {
	function get_title();
	function render( $key, $p2p_id, $post_id );
}


class P2P_Box {
	private $box_id;

	private $data;

	private $current_ptype;
	private $direction;

	public $ptype;

	private $columns;

	private static $extra_qv = array(
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'post_status' => 'any',
	);

	function __construct( $box_id, $data, $current_ptype, $direction ) {
		$this->box_id = $box_id;
		$this->data = $data;

		$this->direction = $direction;

		$this->current_ptype = $current_ptype;

		$other_ptype = $this->data->get_other_post_type( $direction );
		$this->ptype = get_post_type_object( $other_ptype[0] );

		if ( !class_exists( 'Mustache' ) )
			require dirname(__FILE__) . '/../mustache/Mustache.php';

		$this->columns = array(
			'delete' => new P2P_Field_Delete,
			'title' => new P2P_Field_Title( $this->ptype->labels->singular_name ),
		);

		foreach ( $this->data->fields as $key => $data ) {
			$this->columns[ $key ] = new P2P_Field_Generic( $data );
		}

		if ( $this->data->sortable && 'from' == $direction ) {
			$this->columns['order'] = new P2P_Field_Order( $this->data->sortable );
		}

		wp_enqueue_style( 'p2p-admin', plugins_url( 'box.css', __FILE__ ), array(), P2P_PLUGIN_VERSION );
		wp_enqueue_script( 'p2p-admin', plugins_url( 'box.js', __FILE__ ), array( 'jquery' ), P2P_PLUGIN_VERSION, true );
		wp_localize_script( 'p2p-admin', 'P2PAdmin', array(
			'nonce' => wp_create_nonce( P2P_BOX_NONCE ),
			'spinner' => admin_url( 'images/wpspin_light.gif' ),
			'deleteConfirmMessage' => __( 'Are you sure you want to delete all connections?', P2P_TEXTDOMAIN ),
		) );

		add_filter( 'posts_search', array( __CLASS__, '_search_by_title' ), 10, 2 );
	}

	public function register() {
		$title = $this->data->get_title( $this->direction );

		if ( empty( $title ) ) {
			$title = sprintf( __( 'Connected %s', P2P_TEXTDOMAIN ), $this->ptype->labels->name );
		}

		add_meta_box(
			'p2p-connections-' . $this->box_id,
			$title,
			array( $this, 'render' ),
			$this->current_ptype,
			$this->data->context,
			'default'
		);
	}

	function render( $post ) {
		$data = array();

		$qv = self::$extra_qv;
		$qv['nopaging'] = true;

		$connected_posts = $this->data->get_connected( $post->ID, $qv, $this->direction )->posts;

		if ( empty( $connected_posts ) )
			$data['hide-connections'] = 'style="display:none"';

		$tbody = '';
		foreach ( $connected_posts as $connected ) {
			$tbody .= $this->connection_row( $connected->p2p_id, $connected->ID );
		}
		$data['tbody'] = $tbody;

		foreach ( $this->columns as $key => $field ) {
			$data['thead'][] = array(
				'column' => $key,
				'title' => $field->get_title()
			);
		}

		$data_attr = array(
			'box_id' => $this->box_id,
			'prevent_duplicates' => $this->data->prevent_duplicates,
		);

		$data_attr_str = array();
		foreach ( $data_attr as $key => $value )
			$data_attr_str[] = "data-$key='" . $value . "'";
		$data['attributes'] = implode( ' ', $data_attr_str );

		$data['create-label'] = __( 'Create connections:', P2P_TEXTDOMAIN );

		// Search tab
		$tab_content = _p2p_mustache_render( 'tab-search.html', array(
			'placeholder' => $this->ptype->labels->search_items,
		) );

		$data['tabs'][] = array(
			'tab-id' => 'search',
			'tab-title' => __( 'Search', P2P_TEXTDOMAIN ),
			'is-active' => array(true),
			'tab-content' => $tab_content
		);

		// List tab
		$data['tabs'][] = array(
			'tab-id' => 'list',
			'tab-title' => __( 'View All', P2P_TEXTDOMAIN ),
			'tab-content' => $this->post_rows( $post->ID )
		);

		// Create post tab
		if ( $this->data->can_create_post( $this->direction ) ) {
			$tab_content = _p2p_mustache_render( 'tab-create-post.html', array(
				'title' => $this->ptype->labels->add_new_item
			) );

			$data['tabs'][] = array(
				'tab-id' => 'create-post',
				'tab-title' => $this->ptype->labels->new_item,
				'tab-content' => $tab_content
			);
		}

		echo _p2p_mustache_render( 'box.html', $data );
	}

	protected function connection_row( $p2p_id, $post_id ) {
		$data = array();

		foreach ( $this->columns as $key => $field ) {
			$data['columns'][] = array(
				'column' => $key,
				'content' => $field->render( $key, $p2p_id, $post_id )
			);
		}

		return _p2p_mustache_render( 'table-row.html', $data );
	}

	protected function post_rows( $current_post_id, $page = 1, $search = '' ) {
		$args = array_merge( self::$extra_qv, array(
			'paged' => $page,
			'posts_per_page' => 5,
		) );

		if ( $search ) {
			$args['_p2p_box'] = true;
			$args['s'] = $search;
		}

		$query = $this->data->get_connectable( $current_post_id, $args, $this->direction );

		if ( empty( $query->posts ) )
			return false;

		$candidate = (object) array(
			'posts' => $query->posts,
			'current_page' => max( 1, $query->get('paged') ),
			'total_pages' => $query->max_num_pages
		);

		$data = array();

		$columns = array(
			'create' => new P2P_Field_Create,
			'title' => new P2P_Field_Title,
		);

		foreach ( $candidate->posts as $post ) {
			$row = array();

			foreach ( $columns as $key => $field ) {
				$row['columns'][] = array(
					'column' => $key,
					'content' => $field->render( $key, 0, $post->ID )
				);
			}

			$data['rows'][] = $row;
		}

		if ( $candidate->total_pages > 1 ) {
			$data['navigation'] = array(
				'current-page' => $candidate->current_page,
				'total-pages' => $candidate->total_pages,

				'prev-inactive' => ( 1 == $candidate->current_page ) ? 'inactive' : '',
				'next-inactive' => ( $candidate->total_pages == $candidate->current_page ) ? 'inactive' : '',

				'prev-label' =>  __( 'previous', P2P_TEXTDOMAIN ),
				'next-label' =>  __( 'next', P2P_TEXTDOMAIN ),
				'of-label' => __( 'of', P2P_TEXTDOMAIN ),
			);
		}

		return _p2p_mustache_render( 'tab-list.html', $data, array( 'table-row' ) );
	}


	// Ajax handlers

	public function ajax_create_post() {
		$this->safe_connect( $this->create_post( $_POST['post_title'] ) );
	}

	private function create_post( $title ) {
		$args = array(
			'post_title' => $title,
			'post_author' => get_current_user_id(),
			'post_type' => $this->ptype->name
		);

		$args = apply_filters( 'p2p_new_post_args', $args, $this->data );

		return wp_insert_post( $args );
	}

	public function ajax_connect() {
		$this->safe_connect( $_POST['to'] );
	}

	private function safe_connect( $to ) {
		$from = absint( $_POST['from'] );
		$to = absint( $to );

		if ( !$from || !$to )
			die(-1);

		$p2p_id = $this->data->connect( $from, $to, $this->direction );

		die( $this->connection_row( $p2p_id, $to ) );
	}

	public function ajax_disconnect() {
		$this->data->delete_connection( $_POST['p2p_id'] );

		die(1);
	}

	public function ajax_clear_connections() {
		$this->data->disconnect( $_POST['post_id'], $this->direction );

		die(1);
	}

	public function ajax_search() {
		$rows = $this->post_rows( $_GET['post_id'], $_GET['paged'], $_GET['s'] );

		if ( $rows ) {
			$results = compact( 'rows' );
		} else {
			$results = array(
				'msg' => $this->ptype->labels->not_found,
			);
		}

		die( json_encode( $results ) );
	}

	function _search_by_title( $sql, $wp_query ) {
		if ( $wp_query->is_search && $wp_query->get( '_p2p_box' ) ) {
			list( $sql ) = explode( ' OR ', $sql, 2 );
			return $sql . '))';
		}

		return $sql;
	}
}


// Helpers

function _p2p_mustache_render( $file, $data, $partials = array() ) {
	$partial_data = array();
	foreach ( $partials as $partial ) {
		$partial_data[$partial] = _p2p_load_template( $partial . '.html' );
	}

	$m = new Mustache;

	return $m->render( _p2p_load_template( $file ), $data, $partial_data );
}

function _p2p_load_template( $file ) {
	return file_get_contents( dirname(__FILE__) . '/templates/' . $file );
}

