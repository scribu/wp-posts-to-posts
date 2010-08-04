<?php

class P2P_Admin {

	private static $connections;

	function init( $file ) {
		add_action( 'admin_print_styles-post.php', array( __CLASS__, 'scripts' ) );
		add_action( 'admin_print_styles-post-new.php', array( __CLASS__, 'scripts' ) );

		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );

		add_action( 'save_post', array( __CLASS__, 'save' ), 10 );
		add_action( 'wp_ajax_p2p_search', array( __CLASS__, 'ajax_search' ) );

		add_action( 'admin_notices', array( __CLASS__, 'migrate' ) );
	}

	function migrate() {
		if ( !isset( $_GET['migrate_p2p'] ) || !current_user_can( 'administrator' ) )
			return;

		global $wpdb;

		$rows = $wpdb->get_results( "
			SELECT post_id as post_a, meta_value as post_b
			FROM $wpdb->postmeta
			WHERE meta_key = '_p2p'
		" );

		$grouped = array();
		foreach ( $rows as $row )
			$grouped[ $row->post_a ][] = $row->post_b;

		foreach ( $grouped as $post_a => $post_b )
			p2p_connect( $post_a, $post_b );

		$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key = '_p2p'" );

		printf( "<div class='updated'><p>Migrated %s connections.</p></div>", count( $rows ) );
	}

	function scripts() {
		wp_enqueue_script( 'p2p-admin-js', plugins_url( 'admin.js', __FILE__ ), array( 'jquery' ), '0.2', true );

?>
<style type="text/css">
.p2p_connected {margin: 10px 4px}
.p2p_results {margin: -5px 6px 10px}
.p2p_metabox .waiting {vertical-align: -.4em}
</style>
<?php
	}

	function save( $post_a ) {
		$current_ptype = get_post_type( $post_a );
		if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || empty( $_POST ) || 'revision' == $current_ptype )
			return;

		self::cache_connections( $post_a );

		foreach ( p2p_get_connection_types( $current_ptype ) as $post_type ) {
			if ( !isset( $_POST['p2p_connected_ids_' . $post_type] ) )
				continue;

			$reciprocal = p2p_connection_type_is_reciprocal( $current_ptype, $post_type );

			$old_connections = (array) @self::$connections[ $post_type ];
			$new_connections = explode( ',', $_POST[ 'p2p_connected_ids_' . $post_type ] );

			p2p_disconnect( $post_a, array_diff( $old_connections, $new_connections ), $reciprocal );
			p2p_connect( $post_a, array_diff( $new_connections, $old_connections ), $reciprocal );
		}
	}

	private function cache_connections( $post_id ) {
		$posts = p2p_get_connected( $post_id, 'from', 'any', 'objects' );

		$connections = array();
		foreach ( $posts as $post )
			$connections[ $post->post_type ][] = $post->ID;

		self::$connections = $connections;
	}

	function register( $post_type ) {
		global $post;

		self::cache_connections( $post->ID );

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

		$connected_ids = @self::$connections[ $post_type ];

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

		<?php echo html( 'p class="p2p_search"',
			scbForms::input( array(
				'type' => 'text',
				'name' => 'p2p_search_' . $post_type,
				'desc' => __( 'Search', 'posts-to-posts' ) . ':',
				'desc_pos' => 'before',
				'extra' => array( 'autocomplete' => 'off' ),
			) )
			. '<img alt="" src="' . admin_url( 'images/wpspin_light.gif' ) . '" class="waiting" style="display: none;">'
		); ?>

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
		$post_type_name = $_GET['post_type'];

		if ( !post_type_exists( $post_type_name ) )
			die;

		$args = array(
			's' => $_GET['q'],
			'post_type' => $post_type_name,
			'post_status' => 'any',
			'posts_per_page' => 5,
			'order' => 'ASC',
			'orderby' => 'title',
			'suppress_filters' => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false
		);

		$posts = new WP_Query( $args );

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
}

