<?php

/**
 * @package Administration
 */
interface P2P_Field {
	function get_title();
	function render( $key, $p2p_id, $post_id );
}

/**
 * @package Administration
 */
class P2P_Box {
	private $ctype;

	private $args;

	private $ptype;

	private $columns;

	private static $extra_qv = array(
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'post_status' => 'any',
	);

	function __construct( $args, $ctype ) {
		$this->args = $args;

		$this->ctype = $ctype;

		$this->ptype = P2P_Util::get_first_valid_ptype( $this->ctype->get_other_post_type() );

		add_filter( 'posts_search', array( __CLASS__, '_search_by_title' ), 10, 2 );

		$this->init_columns();
	}

	public function init_scripts() {
		wp_enqueue_style( 'p2p-admin', plugins_url( 'box.css', __FILE__ ), array(), P2P_PLUGIN_VERSION );

		wp_enqueue_script( 'p2p-admin', plugins_url( 'box.js', __FILE__ ), array( 'jquery' ), P2P_PLUGIN_VERSION, true );
		wp_localize_script( 'p2p-admin', 'P2PAdmin', array(
			'nonce' => wp_create_nonce( P2P_BOX_NONCE ),
			'spinner' => admin_url( 'images/wpspin_light.gif' ),
			'deleteConfirmMessage' => __( 'Are you sure you want to delete all connections?', P2P_TEXTDOMAIN ),
		) );
	}

	protected function init_columns() {
		$this->columns = array(
			'delete' => new P2P_Field_Delete,
			'title' => new P2P_Field_Title( $this->ptype->labels->singular_name ),
		);

		foreach ( $this->args->fields as $key => $data ) {
			$this->columns[ $key ] = new P2P_Field_Generic( $data );
		}

		if ( method_exists( $this->ctype, 'get_orderby_key' ) ) {
			$this->columns['order'] = new P2P_Field_Order( $this->ctype->get_orderby_key() );
		}
	}

	function render( $post ) {
		$qv = self::$extra_qv;
		$qv['nopaging'] = true;

		$this->connected_posts = $this->ctype->get_connected( $post->ID, $qv )->posts;

		$data = array(
			'p2p-type' => $this->ctype->name,
			'attributes' => $this->render_data_attributes(),
			'connections' => $this->render_connections_table( $post ),
			'create-connections' => $this->render_create_connections( $post ),
		);

		echo P2P_Mustache::render( 'box', $data );
	}

	protected function render_data_attributes() {
		$data_attr = array(
			'prevent_duplicates' => $this->ctype->prevent_duplicates,
			'cardinality' => $this->ctype->accepts_single_connection() ? 'one' : 'many',
			'direction' => $this->ctype->get_direction()
		);

		$data_attr_str = array();
		foreach ( $data_attr as $key => $value )
			$data_attr_str[] = "data-$key='" . $value . "'";

		return implode( ' ', $data_attr_str );
	}

	protected function render_connections_table( $post ) {
		$data = array();

		if ( empty( $this->connected_posts ) )
			$data['hide'] = 'style="display:none"';

		$tbody = array();
		foreach ( $this->connected_posts as $connected ) {
			$tbody[] = $this->connection_row( $connected->p2p_id, $connected->ID );
		}
		$data['tbody'] = $tbody;

		foreach ( $this->columns as $key => $field ) {
			$data['thead'][] = array(
				'column' => $key,
				'title' => $field->get_title()
			);
		}

		return $data;
	}

	protected function render_create_connections( $post ) {
		$data = array(
			'label' => __( 'Create connections:', P2P_TEXTDOMAIN )
		);

		if ( $this->ctype->accepts_single_connection() && !empty( $this->connected_posts ) )
			$data['hide'] = 'style="display:none"';

		// Search tab
		$tab_content = P2P_Mustache::render( 'tab-search', array(
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
		if ( $this->can_create_post() ) {
			$tab_content = P2P_Mustache::render( 'tab-create-post', array(
				'title' => $this->ptype->labels->add_new_item
			) );

			$data['tabs'][] = array(
				'tab-id' => 'create-post',
				'tab-title' => $this->ptype->labels->new_item,
				'tab-content' => $tab_content
			);
		}

		return $data;
	}

	protected function connection_row( $p2p_id, $post_id, $render = false ) {
		return $this->table_row( $this->columns, $p2p_id, $post_id, $render );
	}

	protected function table_row( $columns, $p2p_id, $post_id, $render = false ) {
		$data = array();

		foreach ( $columns as $key => $field ) {
			$data['columns'][] = array(
				'column' => $key,
				'content' => $field->render( $key, $p2p_id, $post_id )
			);
		}

		if ( !$render )
			return $data;

		return P2P_Mustache::render( 'table-row', $data );
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

		$query = $this->ctype->get_connectable( $current_post_id, $args );

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
			$data['rows'][] = $this->table_row( $columns, 0, $post->ID );
		}

		if ( $candidate->total_pages > 1 ) {
			$data['navigation'] = array(
				'current-page' => number_format_i18n( $candidate->current_page ),
				'total-pages' => number_format_i18n( $candidate->total_pages ),

				'current-page-raw' => $candidate->current_page,
				'total-pages-raw' => $candidate->total_pages,

				'prev-inactive' => ( 1 == $candidate->current_page ) ? 'inactive' : '',
				'next-inactive' => ( $candidate->total_pages == $candidate->current_page ) ? 'inactive' : '',

				'prev-label' =>  __( 'previous', P2P_TEXTDOMAIN ),
				'next-label' =>  __( 'next', P2P_TEXTDOMAIN ),
				'of-label' => __( 'of', P2P_TEXTDOMAIN ),
			);
		}

		return P2P_Mustache::render( 'tab-list', $data );
	}


	// Ajax handlers

	public function ajax_create_post() {
		if ( !$this->can_create_post() )
			die( -1 );

		$args = array(
			'post_title' => $_POST['post_title'],
			'post_author' => get_current_user_id(),
			'post_type' => $this->ptype->name
		);

		$args = apply_filters( 'p2p_new_post_args', $args, $this->ctype );

		$this->safe_connect( wp_insert_post( $args ) );
	}

	public function ajax_connect() {
		$this->safe_connect( $_POST['to'] );
	}

	private function safe_connect( $to ) {
		$from = absint( $_POST['from'] );
		$to = absint( $to );

		if ( !$from || !$to )
			die(-1);

		$p2p_id = $this->ctype->connect( $from, $to );

		if ( $p2p_id )
			echo $this->connection_row( $p2p_id, $to, true );

		die;
	}

	public function ajax_disconnect() {
		p2p_delete_connection( $_POST['p2p_id'] );

		die(1);
	}

	public function ajax_clear_connections() {
		$this->ctype->disconnect_all( $_POST['from'] );

		die(1);
	}

	public function ajax_search() {
		$rows = $this->post_rows( $_GET['from'], $_GET['paged'], $_GET['s'] );

		if ( $rows ) {
			$results = compact( 'rows' );
		} else {
			$results = array(
				'msg' => $this->ptype->labels->not_found,
			);
		}

		die( json_encode( $results ) );
	}

	protected function can_create_post() {
		if ( !$this->args->can_create_post )
			return false;

		$base_qv = $this->ctype->get_opposite( 'query_vars' );

		if ( count( $base_qv ) > 1 )
			return false;

		if ( count( $this->ctype->get_other_post_type() ) > 1 )
			return false;

		return $this->check_capability();
	}

	public function check_capability() {
		return current_user_can( $this->ptype->cap->edit_posts );
	}

	function _search_by_title( $sql, $wp_query ) {
		if ( $wp_query->is_search && $wp_query->get( '_p2p_box' ) ) {
			list( $sql ) = explode( ' OR ', $sql, 2 );
			return $sql . '))';
		}

		return $sql;
	}
}

