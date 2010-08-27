<?php

interface P2P_Box {
	function save( $post_id, $to );
	function box( $post, $args );
}

class P2P_Connection_Types {
	private static $ctypes = array();

	static public function register( $args ) {
		$args = wp_parse_args( $args, array(
			'from' => '',
			'to' => '',
			'box' => 'P2P_Box_Multiple',	// TODO: Use friendlier name
			'title' => '',
		) );

		$args['from'] = (array) $args['from'];
		$args['to'] = (array) $args['to'];

		self::$ctypes[] = (object) $args;
	}

	static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, '_register' ) );
		add_action( 'save_post', array( __CLASS__, '_save' ), 10 );
	}

	static function _register( $post_type ) {
		$i = 0;
		foreach ( self::$ctypes as $ctype ) {
			if ( !in_array( $post_type, $ctype->from ) )
				continue;

			foreach ( $ctype->to as $to ) {
				$title = empty($ctype->title) ? get_post_type_object($to)->labels->name : $ctype->title;

				add_meta_box(
					'p2p-connections-' . $i++,
					$title,
					array( $ctype->box, 'box' ),
					$post_type,
					'side',
					'default',
					$to
				);
			}
		}
	}

	static function _save( $post_id ) {
		$from = get_post_type( $post_id );
		if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || empty( $_POST ) || 'revision' == $from )
			return;

		foreach ( self::$ctypes as $ctype ) {
			if ( !in_array( $from, $ctype->from ) )
				continue;

			foreach ( $ctype->to as $to ) {
				call_user_func( array( $ctype->box, 'save' ), $post_id, $to );
			}
		}
	}
}


class P2P_Box_Multiple implements P2P_Box {

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

	function save( $post_a, $to ) {
		if ( !isset( $_POST['p2p_connected_ids_' . $to] ) )
			return;

		$old_connections = (array) p2p_get_connected( $post_a, 'from', $to, 'ids' );
		$new_connections = explode( ',', $_POST[ 'p2p_connected_ids_' . $to ] );

		p2p_disconnect( $post_a, array_diff( $old_connections, $new_connections ) );
		p2p_connect( $post_a, array_diff( $new_connections, $old_connections ) );
	}

	function box( $post, $args ) {
		$post_type = $args['args'];

		$connected_ids = p2p_get_connected( $post->ID, 'from', $post_type, 'ids' );
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

