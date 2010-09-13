<?php

class P2P_Box_Multiple extends P2P_Box {

	protected $meta_keys = array();

	function init() {
		add_action( 'admin_print_styles-post.php', array( __CLASS__, 'scripts' ) );
		add_action( 'admin_print_styles-post-new.php', array( __CLASS__, 'scripts' ) );

		add_action( 'wp_ajax_p2p_search', array( __CLASS__, 'ajax_search' ) );
	}

	function scripts() {
		wp_enqueue_script( 'p2p-admin-js', plugins_url( 'ui.js', __FILE__ ), array( 'jquery' ), '0.4-alpha6', true );

?>
<style type="text/css">
.p2p_connected {margin: 10px 4px}
.p2p_results {margin: -5px 6px 10px}
.p2p_metabox .waiting {vertical-align: -.4em}
</style>
<?php
	}

	function save( $post_a, $data ) {
		p2p_disconnect( $post_a, $this->direction );

		foreach ( $data[ 'post_id' ] as $i => $post_b ) {
			$meta = array();
			foreach ( $this->meta_keys as $meta_key ) {
				$meta_value = $data[ $meta_key ][ $i ];

				if ( empty( $meta_value ) )
					continue;

				$meta[ $meta_key ] = $meta_value;
			}

			if ( $this->reversed )
				p2p_connect( $post_b, $post_a, $meta );
			else
				p2p_connect( $post_a, $post_b, $meta );
		}
	}

	function box( $post_id ) {
		$connected_ids = $this->get_connected_ids( $post_id );
?>

<div class="p2p_metabox">
	<div class="hide-if-no-js checkboxes">
		<ul class="p2p_connected">
		<?php if ( empty( $connected_ids ) ) { ?>
			<li class="howto"><?php _e( 'No connections.', 'posts-to-posts' ); ?></li>
		<?php } else {
			foreach ( $connected_ids as $p2p_id => $post_b ) {
				$this->connection_template( $post_b, $p2p_id );
			}
		} ?>
		</ul>

		<?php echo html( 'p class="p2p_search"',
			scbForms::input( array(
				'type' => 'text',
				'name' => 'p2p_search_' . $this->to,
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
			'name' => $this->input_name( 'ids' ),
			'value' => implode( ',', $connected_ids ),
			'extra' => array( 'class' => 'p2p_connected_ids' ),
		) ); ?>
		<p class="howto"><?php _e( 'Enter IDs of connected post types separated by commas, or turn on JavaScript!', 'posts-to-posts' ); ?></p>
	</div>

<?php // TODO: move to footer, to avoid $_POST polution ?>
	<div style="display:none" class="connection-template">
		<?php $this->connection_template(); ?>
	</div>
</div>
<?php
	}

	function connection_template( $post_id = 0, $p2p_id = 0 ) {
		if ( $post_id ) {
			$post_title = get_the_title( $post_id );
		} else {
			$post_id = '%post_id%';
			$post_title = '%post_title%';
		}

?>
		<li>
			<label>
				<input type="checkbox" checked="checked" name="<?php echo $this->input_name( array( 'post_id', '' ) ); ?>" value="<?php echo $post_id; ?>">
				<?php echo $post_title; ?>
			</label>
		</li>
<?php
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
}

