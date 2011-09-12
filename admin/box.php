<?php

class P2P_Box_Multiple implements P2P_Box_UI {
	public $box_id;

	private $data;

	private $metabox_args;

	private $ptype;
	private $columns;

	function __construct( $box_id, $data, $metabox_args ) {
		$this->box_id = $box_id;
		$this->data = $data;
		$this->metabox_args = $metabox_args;

		$this->ptype = get_post_type_object( $this->data->to );

		if ( !class_exists( 'Mustache' ) )
			require dirname(__FILE__) . '/../mustache/Mustache.php';

		$this->columns = array_merge(
			array( 'delete' => $this->column_delete_all() ),
			array( 'title' => $this->ptype->labels->singular_name ),
			$this->data->fields
		);

		wp_enqueue_style( 'p2p-admin', plugins_url( 'box.css', __FILE__ ), array(), P2P_PLUGIN_VERSION );
		wp_enqueue_script( 'p2p-admin', plugins_url( 'box.js', __FILE__ ), array( 'jquery' ), P2P_PLUGIN_VERSION, true );
		wp_localize_script( 'p2p-admin', 'P2PAdmin', array(
			'nonce' => wp_create_nonce( P2P_BOX_NONCE ),
			'spinner' => admin_url( 'images/wpspin_light.gif' ),
			'deleteConfirmMessage' => __( 'Are you sure you want to delete all connections?', P2P_TEXTDOMAIN ),
		) );
	}

	function __get( $key ) {
		return $this->metabox_args[ $key ];
	}

	function get_title() {
		$title = $this->title;

		if ( is_array( $title ) ) {
			$key = $this->reversed ? 'to' : 'from';

			if ( isset( $title[ $key ] ) )
				$title = $title[ $key ];
			else
				$title = '';
		}

		if ( empty( $title ) ) {
			$title = sprintf( __( 'Connected %s', P2P_TEXTDOMAIN ), $this->ptype->labels->name );
		}

		return $title;
	}


	// Initial rendering

	function render( $post ) {
		$connected_ids = $this->data->get_current_connections( $post->ID );

		$data = array(
			'create-label' => __( 'Create connections:', P2P_TEXTDOMAIN ),
		);

		if ( empty( $connected_ids ) )
			$data['hide-connections'] = 'style="display:none"';

		foreach ( $this->columns as $key => $title ) {
			$data['thead'][] = array(
				'column' => $key,
				'title' => $title
			);
		}

		$data_attr = array(
			'box_id' => $this->box_id,
			'direction' => $this->data->direction,
			'prevent_duplicates' => $this->data->prevent_duplicates,
		);

		$data_attr_str = array();
		foreach ( $data_attr as $key => $value )
			$data_attr_str[] = "data-$key='" . $value . "'";
		$data['attributes'] = implode( ' ', $data_attr_str );

		$tbody = '';
		foreach ( $connected_ids as $p2p_id => $post_b ) {
			$tbody .= $this->connection_row( $p2p_id, $post_b );
		}
		$data['tbody'] = $tbody;

		// Search tab
		$tab_content = self::mustache_render( 'tab-search.html', array(
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
			'tab-id' => 'recent',
			'tab-title' => __( 'View All', P2P_TEXTDOMAIN ),
			'tab-content' => $this->post_rows( $post_id )
		);

		// Create post tab
		if ( current_user_can( $this->ptype->cap->edit_posts ) ) {
			$tab_content = self::mustache_render( 'tab-create-post.html', array(
				'title' => $this->ptype->labels->add_new_item
			) );

			$data['tabs'][] = array(
				'tab-id' => 'create-post',
				'tab-title' => $this->ptype->labels->new_item,
				'tab-content' => $tab_content
			);
		}

		echo self::mustache_render( 'box.html', $data );
	}

	protected function connection_row( $p2p_id, $post_id ) {
		$data = array();
		foreach ( array_keys( $this->columns ) as $key ) {
			switch ( $key ) {
			case 'title':
				$value = $this->column_title( $post_id );
				break;

			case 'delete':
				$value = $this->column_delete( $p2p_id );
				break;

			default:
				$value = $this->column_default( $p2p_id, $key );
			}

			$data['columns'][] = array(
				'column' => $key,
				'content' => $value
			);
		}

		return self::mustache_render( 'box-row.html', $data );
	}

	protected function post_rows( $current_post_id, $page = 1, $search = '' ) {
		$candidate = $this->data->get_connection_candidates( $current_post_id, $page, $search );

		if ( empty( $candidate->posts ) )
			return false;

		$data = array();

		foreach ( $candidate->posts as $post ) {
			$row = array();

			foreach ( array( 'create', 'title' ) as $key ) {
				$row['columns'][] = array(
					'column' => $key,
					'content' => call_user_func( array( $this, "column_$key" ), $post->ID )
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

				'prev-label' =>  __( 'Previous', P2P_TEXTDOMAIN ),
				'next-label' =>  __( 'Next', P2P_TEXTDOMAIN ),
				'of-label' => __( 'of', P2P_TEXTDOMAIN ),
			);
		}

		return self::mustache_render( 'tab-recent.html', $data, array( 'box-row' ) );
	}


	// Column rendering

	protected function column_title( $post_id ) {
		$data = array(
			'title-attr' => get_post_type_object( get_post_type( $post_id ) )->labels->edit_item,
			'title' => get_post_field( 'post_title', $post_id ),
			'url' => get_edit_post_link( $post_id ),
		);

		$post_status = get_post_status( $post_id );

		if ( 'publish' != $post_status ) {
			$status_obj = get_post_status_object( $post_status );
			if ( $status_obj ) {
				$data['status']['text'] = $status_obj->label;
			}
		}

		return self::mustache_render( 'column-title.html', $data );
	}

	protected function column_create( $post_id ) {
		$data = array(
			'post_id' => $post_id,
			'title' => __( 'Create connection', P2P_TEXTDOMAIN )
		);

		return self::mustache_render( 'column-create.html', $data );
	}

	protected function column_delete( $p2p_id ) {
		$data = array(
			'p2p_id' => $p2p_id,
			'title' => __( 'Delete connection', P2P_TEXTDOMAIN )
		);

		return self::mustache_render( 'column-delete.html', $data );
	}

	protected function column_delete_all() {
		$data = array(
			'title' => __( 'Delete all connections', P2P_TEXTDOMAIN )
		);

		return self::mustache_render( 'column-delete-all.html', $data );
	}

	protected function column_default( $p2p_id, $key ) {
		return html( 'input', array(
			'type' => 'text',
			'name' => "p2p_meta[$p2p_id][$key]",
			'value' => p2p_get_meta( $p2p_id, $key, true )
		) );
	}


	// Ajax handlers

	public function ajax_create_post() {
		$this->safe_connect( $this->data->create_post( $_POST['post_title'] ) );
	}

	public function ajax_connect() {
		$this->safe_connect( $_POST['to'] );
	}

	private function safe_connect( $to ) {
		$from = absint( $_POST['from'] );
		$to = absint( $to );

		if ( !$from || !$to )
			die(-1);

		$p2p_id = $this->data->connect( $from, $to );

		die( $this->connection_row( $p2p_id, $to ) );
	}

	public function ajax_disconnect() {
		$this->data->delete_connection( $_POST['p2p_id'] );

		die(1);
	}

	public function ajax_clear_connections() {
		$this->data->disconnect( $_POST['post_id'] );

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


	// Helpers

	private static function mustache_render( $file, $data, $partials = array() ) {
		$partial_data = array();
		foreach ( $partials as $partial ) {
			$partial_data[$partial] = self::load_template( $partial . '.html' );
		}

		$m = new Mustache;

		return $m->render( self::load_template( $file ), $data, $partial_data );
	}

	private function load_template( $file ) {
		return file_get_contents( dirname(__FILE__) . '/templates/' . $file );
	}
}

