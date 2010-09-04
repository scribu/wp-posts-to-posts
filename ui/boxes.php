<?php

class P2P_Box_Multiple extends P2P_Box {

	function init() {
		add_action( 'admin_print_styles-post.php', array( __CLASS__, 'scripts' ) );
		add_action( 'admin_print_styles-post-new.php', array( __CLASS__, 'scripts' ) );

		add_action( 'wp_ajax_p2p_search', array( __CLASS__, 'ajax_search' ) );
	}

	function scripts() {
		wp_enqueue_script( 'p2p-admin-js', plugins_url( 'ui.js', __FILE__ ), array( 'jquery' ), '0.4-alpha3', true );

?>
<style type="text/css">
.p2p_connected {margin: 10px 4px}
.p2p_results {margin: -5px 6px 10px}
.p2p_metabox .waiting {vertical-align: -.4em}
</style>
<?php
	}

	function save( $post_id ) {
		if ( !isset( $_POST['p2p_connected_ids'][$this->id] ) )
			return;

		$old_connections = self::get_connected_ids( $post_id );
		$new_connections = explode( ',', $_POST['p2p_connected_ids'][$this->id] );

		$to_disconnect = array_diff( $old_connections, $new_connections );
		$to_connect = array_diff( $new_connections, $old_connections );

		if ( $this->reversed ) {
			p2p_disconnect( $to_disconnect, $post_id );
			p2p_connect( $to_connect, $post_id );
		} else {
			p2p_disconnect( $post_id, $to_disconnect );
			p2p_connect( $post_id, $to_connect );
		}
	}

	function box( $post_id ) {
		$post_type = $this->to;

		$connected_ids = self::get_connected_ids( $post_id, $post_type );
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
			'name' => "p2p_connected_ids[$this->id]",
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

	protected function get_connected_ids( $post_id ) {
		$field = $this->reversed ? 'connected_to' : 'connected_from';

		$args = array(
			$field => $post_id,
			'post_type'=> $this->to,
			'post_status' => 'any',
			'nopaging' => true,
			'suppress_filters' => false,
		);

		return scbUtil::array_pluck( get_posts($args), 'ID' );
	}
}

