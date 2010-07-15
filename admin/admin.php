<?php

class P2P_Box {

	function init( $file ) {
		add_action( 'admin_print_styles-post.php', array( __CLASS__, 'scripts' ) );
		add_action( 'admin_print_styles-post-new.php', array( __CLASS__, 'scripts' ) );

		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );

		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'wp_ajax_p2p_search', array( __CLASS__, 'ajax_search' ) );

		scbUtil::add_uninstall_hook( $file, array( __CLASS__, 'uninstall' ) );
	}

	function scripts() {
		wp_enqueue_style( 'p2p-admin-css', plugins_url( 'admin.css', __FILE__ ) );
		wp_enqueue_script( 'p2p-admin-js', plugins_url( 'admin.js', __FILE__ ), array( 'jquery' ), '0.2', true );
	}

	function save( $post_a, $post ) {
		if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || empty( $_POST ) || 'revision' == $post->post_type )
			return;

		$connections = p2p_get_connected( 'any', 'from', $post_a, true );

		foreach ( p2p_get_connection_types( $post->post_type ) as $post_type ) {
			if ( !isset( $_POST['p2p_connected_ids_' . $post_type] ) )
				continue;

			$old_connections = (array) $connections[ $post_type ];
			$new_connections = explode( ',', $_POST[ 'p2p_connected_ids_' . $post_type ] );

			foreach ( array_diff( $old_connections, $new_connections ) as $post_b )
				p2p_disconnect( $post_a, $post_b );

			foreach ( array_diff( $new_connections, $old_connections ) as $post_b )
				p2p_connect( $post_a, $post_b );
		}
	}

	function register( $post_type ) {
		foreach ( p2p_get_connection_types( $post_type ) as $type ) {
			add_meta_box(
				'p2p-connections-' . $type,
				__( 'Connected', 'posts-to-posts' ) . ' ' . get_post_type_object( $type )->labels->name,
				array( __CLASS__, 'box' ),
				$post_type,
				'side',
				'default',
				$type
			);
		}
	}

	function box( $post, $args ) {
		$post_type = $args['args'];
		$connected_ids = p2p_get_connected( $post_type, 'from', $post->ID );
?>

<div class="p2p_metabox">
	<div class="hide-if-no-js checkboxes">
		<ul class="p2p_connected">
		<?php if ( empty( $connected_ids ) ) { ?>
			<li class="howto"><?php _e( 'No connections.', 'posts-to-posts' ); ?></li>
		<?php } else { ?>
			<?php foreach ( $connected_ids as $id ) {
				echo html( 'li', scbForms::input( array(
					'type' => 'checkbox',
					'name' => "p2p_checkbox_$id",
					'value' => $id,
					'checked' => true,
					'desc' => get_the_title( $id ),
					'extra' => array( 'autocomplete' => 'off' ),
				) ) );
			} ?>
		<?php } ?>
		</ul>

		<?php echo html( 'p', scbForms::input( array(
			'type' => 'text',
			'name' => 'p2p_search_' . $post_type,
			'desc' => __( 'Search', 'posts-to-posts' ) . ':',
			'desc_pos' => 'before',
			'extra' => array( 'class' => 'p2p_search', 'autocomplete' => 'off' ),
		) ) ); ?>

		<ul class="p2p_results"></ul>
		<p class="howto"><?php _e( 'Start typing name of connected post type and click on it if you want to connect it.', 'posts-to-posts' ); ?></p>
	</div>

	<div class="hide-if-js">
		<?php echo scbForms::input( array(
			'type' => 'text',
			'name' => 'p2p_connected_ids_' . $post_type,
			'value' => implode( ',', $connected_ids ),
			'extra' => array( 'class' => 'p2p_connected_ids' ),
		) ); ?>
		<p class="howto"><?php _e( 'Enter IDs of connected post types separated by commas, or turn on JavaScript!', 'posts-to-posts' ); ?></p>
	</div>
</div>
<?php
	}

	function ajax_search() {
		$posts = new WP_Query( array(
			'posts_per_page' => 5,
			's' => $_GET['q'],
			'post_type' => $_GET['post_type']
		) );

		$results = array();
		while ( $posts->have_posts() ) {
			$posts->the_post();
			$results[ get_the_ID() ] = get_the_title();
		}

		die( json_encode( $results ) );
	}

	private function get_post_list( $post_type ) {
		$args = array(
			'post_type' => $post_type,
			'post_status' => 'any',
			'nopaging' => true,
			'cache_results' => false,
		);

		return scbUtil::objects_to_assoc( get_posts( $args ), 'ID', 'post_title' );
	}

	function uninstall() {
		global $wpdb;

		$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key = '" . P2P_META_KEY . "'" );
	}
}

