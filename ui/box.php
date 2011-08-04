<?php

class P2P_Box_Multiple extends P2P_Box {
	const POSTS_PER_PAGE = 5;

	function setup() {
		if ( !class_exists( 'Mustache' ) )
			require dirname(__FILE__) . '/../mustache/Mustache.php';

		wp_enqueue_style( 'p2p-admin', plugins_url( 'box.css', __FILE__ ), array(), P2P_PLUGIN_VERSION );
		wp_enqueue_script( 'p2p-admin', plugins_url( 'box.js', __FILE__ ), array( 'jquery' ), P2P_PLUGIN_VERSION, true );
		wp_localize_script( 'p2p-admin', 'P2PAdmin_I18n', array(
			'nonce' => wp_create_nonce( P2P_BOX_NONCE ),
			'deleteConfirmMessage' => __( 'Are you sure you want to delete all connections?', P2P_TEXTDOMAIN ),
		) );

		$this->columns = array_merge(
			array( 'delete' => $this->column_delete_all() ),
			array( 'title' => get_post_type_object( $this->to )->labels->singular_name ),
			$this->fields
		);
	}


	// Initial rendering

	function render_box( $post_id ) {
		$connected_ids = $this->get_connected_ids( $post_id );

		$to_cpt = get_post_type_object( $this->to );

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

		$data_attr = array();
		foreach ( array( 'box_id', 'direction', 'prevent_duplicates' ) as $key )
			$data_attr[] = "data-$key='" . $this->$key . "'";
		$data['attributes'] = implode( ' ', $data_attr );

		$tbody = '';
		foreach ( $connected_ids as $p2p_id => $post_b ) {
			$tbody .= $this->connection_row( $p2p_id, $post_b );
		}
		$data['tbody'] = $tbody;

		// Search tab
		$tab_content = self::mustache_render( 'tab-search.html', array(
			'placeholder' => $to_cpt->labels->search_items,
		) );

		$data['tabs'][] = array(
			'tab-id' => 'search',
			'tab-title' => __( 'Search', P2P_TEXTDOMAIN ),
			'is-active' => array(true),
			'tab-content' => $tab_content
		);

		// Recent tab
		$data['tabs'][] = array(
			'tab-id' => 'recent',
			'tab-title' => __( 'Recent', P2P_TEXTDOMAIN ),
			'tab-content' => $this->handle_search( $post_id )
		);

		// Create post tab
		if ( current_user_can( $to_cpt->cap->edit_posts ) ) {
			$tab_content = self::mustache_render( 'tab-create-post.html', array(
				'title' => $to_cpt->labels->add_new_item
			) );

			$data['tabs'][] = array(
				'tab-id' => 'create-post',
				'tab-title' => $to_cpt->labels->new_item,
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

	protected function post_rows( $query ) {
		$data = array();

		foreach ( $query->posts as $post ) {
			$row = array();

			foreach ( array( 'create', 'title' ) as $key ) {
				$row['columns'][] = array(
					'column' => $key,
					'content' => call_user_func( array( $this, "column_$key" ), $post->ID )
				);
			}

			$data['rows'][] = $row;
		}

		$current_page = max( 1, $query->get('paged') );
		$total_pages = $query->max_num_pages;

		if ( $total_pages > 1 ) {
			$data['navigation'] = array(
				'current-page' => $current_page,
				'total-pages' => $total_pages,

				'prev-inactive' => ( 1 == $current_page ) ? 'inactive' : '',
				'next-inactive' => ( $total_pages == $current_page ) ? 'inactive' : '',

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
		$new_post_id = wp_insert_post( array(
			'post_title' => $_POST['post_title'],
			'post_author' => get_current_user_id(),
			'post_type' => $this->to
		) );

		$this->safe_connect( absint( $_POST['from'] ), $new_post_id );
	}

	public function ajax_connect() {
		$this->safe_connect( absint( $_POST['from'] ), absint( $_POST['to'] ) );
	}

	protected function safe_connect( $from, $to ) {
		if ( !$from || !$to )
			die(-1);

		$args = array( $from, $to );

		if ( $this->reversed )
			$args = array_reverse( $args );

		$p2p_id = false;
		if ( $this->prevent_duplicates ) {
			$p2p_ids = P2P_Connections::get( $args[0], $args[1], $this->data );

			if ( !empty( $p2p_ids ) )
				$p2p_id = $p2p_ids[0];
		}

		if ( !$p2p_id ) {
			$p2p_id = P2P_Connections::connect( $args[0], $args[1], $this->data );
		}

		die( $this->connection_row( $p2p_id, $to ) );
	}

	public function ajax_disconnect() {
		$p2p_id = absint( $_POST['p2p_id'] );

		p2p_delete_connection( $p2p_id );

		die(1);
	}

	public function ajax_clear_connections() {
		$post_id = absint( $_POST['post_id'] );

		p2p_disconnect( $post_id, $this->direction );

		die(1);
	}

	public function ajax_search() {
		$rows = $this->handle_search( $_GET['post_id'], $_GET['paged'], $_GET['s'] );

		if ( $rows ) {
			$results = compact( 'rows' );
		} else {
			$results = array(
				'msg' => get_post_type_object( $this->to )->labels->not_found,
			);
		}

		die( json_encode( $results ) );
	}

	protected function handle_search( $post_id, $page = 1, $search = '' ) {
		$query = new WP_Query( $this->get_query_vars( $post_id, $page, $search ) );

		if ( !$query->have_posts() )
			return false;

		return $this->post_rows( $query );
	}

	protected function get_query_vars( $post_id, $page, $search ) {
		$args = array(
			'paged' => $page,
			'post_type' => $this->to,
			'post_status' => 'any',
			'posts_per_page' => self::POSTS_PER_PAGE,
			'suppress_filters' => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false
		);

		if ( $search ) {
			add_filter( 'posts_search', array( __CLASS__, '_search_by_title' ), 10, 2 );
			$args['s'] = $search;
		}

		if ( $this->prevent_duplicates )
			$args['post__not_in'] = $this->get_connected( $post_id );

		return $args;
	}

	function _search_by_title( $sql, $wp_query ) {
		if ( $wp_query->is_search ) {
			list( $sql ) = explode( ' OR ', $sql, 2 );
			return $sql . '))';
		}

		return $sql;
	}


	// Helpers

	protected function get_connected_ids( $post_id ) {
		$connected_posts = $this->get_connected( $post_id );

		if ( empty( $connected_posts ) )
			return array();

		$args = array(
			'post__in' => $connected_posts,
			'post_type'=> $this->to,
			'post_status' => 'any',
			'nopaging' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters' => false,
		);

		$post_ids = wp_list_pluck( get_posts($args), 'ID' );

		return array_intersect( $connected_posts, $post_ids );	// to preserve p2p_id keys
	}

	protected function get_connected( $post_id ) {
		return P2P_Connections::get( $post_id, $this->direction, $this->data );
	}

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

