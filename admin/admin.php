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

		$connections = p2p_get_connected( 'any', 'from', $post->ID );

		foreach ( p2p_get_connection_types( $post->post_type ) as $post_type ) {
			if ( !isset( $_POST['p2p_connected_ids_' . $post_type] ) )
				continue;

			$connected_ids = explode( ',', $_POST[ 'p2p_connected_ids_' . $post_type ] );

			foreach ( (array) $connections[ $post_type ] as $post_b ) {
				if ( false === array_search( $post_b, $connected_ids ) )
					p2p_disconnect( $post_a, $post_b );
			}

			foreach ( $connected_ids as $post_b ) {
				if ( false === array_search( $post_b, $connections[ $post_type ] ) ) {
					p2p_connect( $post_a, $post_b );
				}
			}
		}
	}

	function register( $post_type ) {
		foreach ( p2p_get_connection_types( $post_type ) as $type ) {
			add_meta_box(
				'p2p-connections-' . $type,
				get_post_type_object( $type )->labels->singular_name . ' ' . __( 'connections', 'posts-to-posts' ),
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
		$ptype_name = get_post_type_object( $post_type )->labels->name;
		$connected_ids = p2p_get_connected( $post_type, 'from', $post->ID );
?>

		<div class="p2p_metabox">
			<div class="hide-if-no-js checkboxes">
			<?php if ( empty( $connected_ids ) ) { ?>
				<p class="howto"><?php _e( 'No connections.', 'posts-to-posts' ); ?></p>
			<?php } else { ?>
				<p><?php _e( 'Connected', 'posts-to-posts' ); ?> <?php echo $ptype_name; ?>:</p>
				<div>
					<?php foreach ( $connected_ids as $id ) { ?>
						<?php $id_name = "p2p_checkbox_$id"; ?>
						<input type="checkbox" name="<?php echo $id_name; ?>" id="<?php echo $id_name; ?>" value="<?php echo $id;?>" checked="checked"> <label for="<?php echo $id_name;?>"><?php echo get_the_title( $id )?></label><br/>
					<?php } ?>
				</div>
			<?php } ?>
			</div>

			<div class="hide-if-js">
			<input type="text" class="p2p_connected_ids" name="p2p_connected_ids_<?php echo $post_type; ?>" value="<?php echo implode( ',', $connected_ids );?>" />
			<p class="howto"><?php _e( 'Enter IDs of connected post types separated by commas, or turn on JavaScript!', 'posts-to-posts' ); ?></p>
			</div>
			<div class="hide-if-no-js">
				<p>
				<label><?php _e( 'Search', 'posts-to-posts' ); ?> <?php echo $ptype_name; ?>:</label>
				<input type="text" name="p2p_search_<?php echo $post_type;?>" id="p2p_search_<?php echo $post_type;?>" class="p2p_search" />
				</p>
				<div>
					<ul class="results">

					</ul>
				</div>
				<p class="howto"><?php _e( 'Start typing name of connected post type and click on it if you want to connect it.', 'posts-to-posts' ); ?></p>
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

