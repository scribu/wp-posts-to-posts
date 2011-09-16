<?php

interface P2P_Field {
	function get_title();
	function render( $key, $p2p_id, $post_id );
}


class P2P_Box {
	private $box_id;

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

		$this->columns = array(
			'delete' => new P2P_Field_Delete,
			'title' => new P2P_Field_Title( $this->ptype->labels->singular_name ),
		);

		foreach ( $this->data->fields as $key => $data ) {
			$this->columns[ $key ] = new P2P_Field_Generic( $data );
		}

		if ( $this->data->sortable ) {
			$this->columns['order'] = new P2P_Field_Order( $this->data->sortable );
		}

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

	public function register() {
		$title = $this->data->title;

		if ( empty( $title ) ) {
			$title = sprintf( __( 'Connected %s', P2P_TEXTDOMAIN ), $this->ptype->labels->name );
		}

		add_meta_box(
			'p2p-connections-' . $this->box_id,
			$title,
			array( $this, 'render' ),
			$this->data->from,
			$this->context,
			'default'
		);
	}

	function render( $post ) {
		$connected_ids = $this->data->get_current_connections( $post->ID );

		$data = array(
			'create-label' => __( 'Create connections:', P2P_TEXTDOMAIN ),
		);

		if ( empty( $connected_ids ) )
			$data['hide-connections'] = 'style="display:none"';

		foreach ( $this->columns as $key => $field ) {
			$data['thead'][] = array(
				'column' => $key,
				'title' => $field->get_title()
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
			'tab-id' => 'recent',
			'tab-title' => __( 'View All', P2P_TEXTDOMAIN ),
			'tab-content' => $this->post_rows( $post->ID )
		);

		// Create post tab
		if ( current_user_can( $this->ptype->cap->edit_posts ) ) {
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

		return _p2p_mustache_render( 'box-row.html', $data );
	}

	protected function post_rows( $current_post_id, $page = 1, $search = '' ) {
		$candidate = $this->data->get_connection_candidates( $current_post_id, $page, $search );

		if ( empty( $candidate->posts ) )
			return false;

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

		return _p2p_mustache_render( 'tab-recent.html', $data, array( 'box-row' ) );
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

