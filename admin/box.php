<?php

/**
 * @package Administration
 */
interface P2P_Field {
	function get_title();
	function render( $p2p_id, $post_id );
}

/**
 * @package Administration
 */
class P2P_Box {
	private $ctype;

	private $args;

	private $ptype;

	private $columns;

	function __construct( $args, $ctype ) {
		$this->args = $args;

		$this->ctype = $ctype;

		$this->labels = $this->ctype->get_opposite( 'labels' );

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
		$title_class = $this->get_column_title_class();

		$this->columns = array(
			'delete' => new P2P_Field_Delete,
			'title' => new $title_class( $this->labels->singular_name ),
		);

		foreach ( $this->args->fields as $key => $data ) {
			$this->columns[ 'meta-' . $key ] = new P2P_Field_Generic( $key, $data );
		}

		if ( $orderby_key = $this->ctype->get_orderby_key() ) {
			$this->columns['order'] = new P2P_Field_Order( $orderby_key );
		}
	}

	protected function get_column_title_class() {
		$object_type = $this->ctype->get_opposite( 'object' );

		return 'P2P_Field_Title_' . ucfirst( $object_type );
	}

	function render( $post ) {
		$this->connected_items = $this->ctype->get_connections( $post->ID );

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

		if ( empty( $this->connected_items ) )
			$data['hide'] = 'style="display:none"';

		$tbody = array();
		foreach ( $this->connected_items as $p2p_id => $item_id ) {
			$tbody[] = $this->connection_row( $p2p_id, $item_id );
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

		if ( $this->ctype->accepts_single_connection() && !empty( $this->connected_items ) )
			$data['hide'] = 'style="display:none"';

		// Search tab
		$tab_content = P2P_Mustache::render( 'tab-search', array(
			'placeholder' => $this->labels->search_items,
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
				'title' => $this->labels->add_new_item
			) );

			$data['tabs'][] = array(
				'tab-id' => 'create-post',
				'tab-title' => $this->labels->new_item,
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
				'content' => $field->render( $p2p_id, $post_id )
			);
		}

		if ( !$render )
			return $data;

		return P2P_Mustache::render( 'table-row', $data );
	}

	protected function post_rows( $current_post_id, $page = 1, $search = '' ) {
		$candidate = $this->ctype->get_connectable( $current_post_id, $page, $search );

		if ( empty( $candidate->items ) )
			return false;

		$data = array();

		$columns = array(
			'create' => new P2P_Field_Create,
			'title' => $this->columns['title']
		);

		foreach ( $candidate->items as $item ) {
			$data['rows'][] = $this->table_row( $columns, 0, $item->ID );
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
			'post_type' => $this->ctype->get_opposite( 'side' )->post_type[0]
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
			$r = array( 'row' => $this->connection_row( $p2p_id, $to, true ) );
		else
			$r = array( 'error' => __( "Can't create connection.", P2P_TEXTDOMAIN ) );

		die( json_encode( $r ) );
	}

	public function ajax_disconnect() {
		p2p_delete_connection( $_POST['p2p_id'] );

		$this->refresh_candidates();
	}

	public function ajax_clear_connections() {
		$this->ctype->disconnect_all( $_POST['from'] );

		$this->refresh_candidates();
	}

	public function ajax_search() {
		die( json_encode( $this->_ajax_search( $_GET ) ) );
	}

	private function refresh_candidates() {
		$results = array();

		foreach ( array( 'search', 'all' ) as $key ) {
			$args = $_POST[ $key ];
			$args['from'] = $_POST['from'];

			if ( 'search' == $key && empty( $args['s'] ) )
				$results[ $key ] = array();
			else
				$results[ $key ] = $this->_ajax_search( $args );
		}

		die( json_encode( $results ) );
	}

	private function _ajax_search( $args ) {
		$rows = $this->post_rows( $args['from'], $args['paged'], $args['s'] );

		if ( $rows ) {
			$results = compact( 'rows' );
		} else {
			$results = array(
				'msg' => $this->labels->not_found,
			);
		}

		return $results;
	}

	protected function can_create_post() {
		if ( !$this->args->can_create_post )
			return false;

		if ( 'post' != $this->ctype->get_opposite( 'object' ) )
			return false;

		$side = $this->ctype->get_opposite( 'side' );

		if ( count( $side->post_type ) > 1 )
			return false;

		if ( count( $side->query_vars ) > 1 )
			return false;

		return true;
	}

	public function check_capability() {
		return $this->ctype->get_opposite( 'side' )->check_capability();
	}
}

