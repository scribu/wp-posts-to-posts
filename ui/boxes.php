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
		if ( !empty( $data['all'] ) )
			p2p_delete_connection( array_diff( $data['all'], (array) @$data['enabled'] ) );

		foreach ( explode( ',', $data[ 'ids' ] ) as $i => $post_b ) {
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

		foreach ( array_keys( $connected_ids ) as $p2p_id ) { ?>
			<input type="hidden" name="<?php echo $this->input_name( array( 'all', '' ) ); ?>" value="<?php echo $p2p_id; ?>">
		<?php } ?>

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
		<p class="howto"><?php _e( 'Start typing the title of a post you want to connect and then click on to connect it.', 'posts-to-posts' ); ?></p>
	</div>

	<div class="hide-if-js">
		<?php echo scbForms::input( array(
			'type' => 'text',
			'name' => $this->input_name( 'ids' ),
			'value' => '',
			'extra' => array( 'class' => 'p2p_to_connect' ),
		) ); ?>
		<p class="howto"><?php _e( 'Enter IDs of posts to connect, separated by commas.', 'posts-to-posts' ); ?></p>
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
				<input type="checkbox" checked="checked" name="<?php echo $this->input_name( array( 'enabled', '' ) ); ?>" value="<?php echo $p2p_id; ?>">
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

		add_filter( 'posts_search', array( __CLASS__, 'only_search_by_title' ) );

		$args = array(
			's' => $_GET['q'],
			'post_type' => $post_type_name,
			'post_status' => 'any',
			'posts_per_page' => 5,
			'order' => 'ASC',
			'orderby' => 'title',
			'suppress_filters' => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false
		);

		$posts = get_posts( $args );

		$results = array();
		foreach ( $posts as $post )
			$results[ $post->ID ] = $post->post_title;

		die( json_encode( $results ) );
	}

	function only_search_by_title( $sql ) {
		remove_filter( current_filter(), array( __CLASS__, __FUNCTION__ ) );

		list( $sql ) = explode( ' OR ', $sql, 2 );

		return $sql . '))';
	}
}

