<?php

class P2P_Box_Multiple extends P2P_Box {

	function setup() {
		if ( !class_exists( 'Mustache' ) )
			require dirname(__FILE__) . '/../mustache/Mustache.php';

		wp_enqueue_style( 'p2p-admin', plugins_url( 'box.css', __FILE__ ), array(), P2P_PLUGIN_VERSION );
		wp_enqueue_script( 'p2p-admin', plugins_url( 'box.js', __FILE__ ), array( 'jquery' ), P2P_PLUGIN_VERSION, true );
		wp_localize_script( 'p2p-admin', 'P2PAdmin_I18n', array(
			'deleteConfirmMessage' => __( 'Are you sure you want to delete all connections?', 'posts-to-posts' ),
		) );

		$this->columns = array_merge(
			array( 'delete' => $this->column_delete_all() ),
			array( 'title' => get_post_type_object( $this->to )->labels->singular_name ),
			$this->fields
		);
	}

	function create_post() {
		$new_post_id = wp_insert_post( array(
			'post_title' => $_POST['post_title'],
			'post_author' => 1,
			'post_type' => $this->to
		) );

		$this->safe_connect( absint( $_POST['from'] ), $new_post_id );
	}

	function connect() {
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
			$p2p_ids = P2P_Connections::get( $args[0], $args[1] );

			if ( !empty( $p2p_ids ) )
				$p2p_id = $p2p_ids[0];
		}

		if ( !$p2p_id )
			$p2p_id = P2P_Connections::connect( $args[0], $args[1] );

		$this->connection_row( $p2p_id, $to );
	}

	function disconnect() {
		$p2p_id = absint( $_POST['p2p_id'] );

		p2p_delete_connection( $p2p_id );

		die(1);
	}

	function clear_connections() {
		$post_id = absint( $_POST['post_id'] );

		p2p_disconnect( $post_id, $this->direction );

		die(1);
	}

	function box( $post_id ) {
		$connected_ids = $this->get_connected_ids( $post_id );

		$to_cpt = get_post_type_object( $this->to );

		$data = array(
			'search-key' => 'p2p_search_' . $this->to,
			'create' => __( 'Create connections:', 'posts-to-posts' ),
			'placeholder' => $to_cpt->labels->search_items,

			'prev' =>  __( 'Previous', 'p2p-textdomain' ),
			'next' =>  __( 'Next', 'p2p-textdomain' ),
			'of' => __( 'of', 'p2p-textdomain' ),
			'recent' => __( 'Recent', 'p2p-textdomain' ),
		);

		if ( empty( $connected_ids ) )
			$data['hide-connections'] = 'style="display:none"';

		foreach ( $this->columns as $key => $title ) {
			$data['thead'][] = array(
				'class' => "p2p-col-$key",
				'title' => $title
			);
		}

		$data_attr = array();
		foreach ( array( 'box_id', 'direction', 'prevent_duplicates' ) as $key )
			$data_attr[] = "data-$key='" . $this->$key . "'";
		$data['attributes'] = implode( ' ', $data_attr );

		ob_start();
		foreach ( $connected_ids as $p2p_id => $post_b ) {
			$this->connection_row( $p2p_id, $post_b );
		}
		$data['tbody'] = ob_get_clean();

		$data['tabs'][] = array(
			'ref' => '.p2p-create-connections',
			'text' => __( 'Search', 'p2p-textdomain' ),
			'is-active' => array(true)
		);

		$data['tabs'][] = array(
			'ref' => '.p2p-recent',
			'text' => __( 'Recent', 'p2p-textdomain' ),
		);

		if ( current_user_can( $to_cpt->cap->edit_posts ) ) {
			$data['tabs'][] = array(
				'ref' => '.p2p-create-post',
				'text' => $to_cpt->labels->new_item
			);

			$data['create-post'] = array(
				'key' => 'p2p_new_title_' . $this->to,
				'title' => $to_cpt->labels->add_new_item
			);
		}

		// Render the box
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
				$value = html( 'input', array(
					'type' => 'text',
					'name' => "p2p_meta[$p2p_id][$key]",
					'value' => p2p_get_meta( $p2p_id, $key, true )
				) );
			}

			$data['columns'][] = array(
				'class' => "p2p-col-$key",
				'content' => $value
			);
		}

		echo self::mustache_render( 'box-row.html', $data );
	}

	private static function mustache_render( $file, $data ) {
		$template = file_get_contents( dirname(__FILE__) . '/templates/' . $file );
		$m = new Mustache;
		return $m->render( $template, $data );
	}

	public function results_row( $post ) {
		echo '<tr>';

		foreach ( array( 'create', 'title' ) as $key ) {
			$method = "column_$key";
			echo html( 'td', array( 'class' => "p2p-col-$key" ), $this->$method( $post->ID ) );
		}

		echo '</tr>';
	}

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
			'title' => __( 'Create connection', 'posts-to-posts' )
		);

		return self::mustache_render( 'column-create.html', $data );
	}

	protected function column_delete( $p2p_id ) {
		$data = array(
			'p2p_id' => $p2p_id,
			'title' => __( 'Delete connection', 'posts-to-posts' )
		);

		return self::mustache_render( 'column-delete.html', $data );
	}

	protected function column_delete_all() {
		$data = array(
			'title' => __( 'Delete all connections', 'posts-to-posts' )
		);

		return self::mustache_render( 'column-delete-all.html', $data );
	}

	protected function get_connected_ids( $post_id ) {
		$connected_posts = p2p_get_connected( $post_id, $this->direction );

		if ( empty( $connected_posts ) )
			return array();

		$args = array(
			'post__in' => $connected_posts,
			'post_type'=> $this->to,
			'post_status' => 'any',
			'nopaging' => true,
			'suppress_filters' => false,
		);

		$post_ids = scbUtil::array_pluck( get_posts($args), 'ID' );

		return array_intersect( $connected_posts, $post_ids );	// to preserve p2p_id keys
	}

	function get_search_args( $args, $post_id ) {
		$args = array_merge( $args, array(
			'post_type' => $this->to,
			'post_status' => 'any',
			'posts_per_page' => 5,
			'suppress_filters' => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false
		) );

		if ( $this->prevent_duplicates )
			$args['post__not_in'] = p2p_get_connected( $post_id, $this->direction );

		return $args;
	}

	function get_recent_args( $post_id ) {
		$args = array(
			'numberposts' => 10,
			'orderby' => 'post_date',
			'order' => 'DESC',
			'post_type' => $this->to,
			'post_status' => 'publish',
			'suppress_filters' => false //true
		);

		if ( $this->prevent_duplicates )
			$args['post__not_in'] = p2p_get_connected( $post_id, $this->direction );

		return $args;
	}
}

