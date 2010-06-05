<?php

class P2P_Box {

	function init($file) {
		add_action('add_meta_boxes', array(__CLASS__, 'register'));
		add_action('save_post', array(__CLASS__, 'save'), 10, 2);

		scbUtil::add_uninstall_hook($file, array(__CLASS__, 'uninstall'));
	}

	function uninstall() {
		global $wpdb;

		$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = '" . P2P_META_KEY . "'");
	}

	function save($post_a, $post) {
		if ( defined('DOING_AJAX') || defined('DOING_CRON') || empty($_POST) || 'revision' == $post->post_type )
			return;

		$connections = p2p_get_connected('any', 'from', $post->ID);

		foreach ( p2p_get_connection_types($post->post_type) as $post_type ) {
			if ( !isset($_POST['p2p'][$post_type]) )
				continue;

			foreach ( $connections[$post_type] as $post_b )
				p2p_disconnect($post_a, $post_b);

			if ( $post_b = absint($_POST['p2p'][$post_type]) )
				p2p_connect($post_a, $post_b);
		}
	}

	function register($post_type) {
		$connection_types = p2p_get_connection_types($post_type);

		if ( empty($connection_types) )
			return;

		add_meta_box('p2p-connections', __('Connections', 'p2p-textdomain'), array(__CLASS__, 'box'), $post_type, 'side');
	}

	function box($post) {
		$connections = p2p_get_connected('any', 'from', $post->ID);

		$out = '';
		foreach ( p2p_get_connection_types($post->post_type) as $post_type ) {
			$posts = self::get_post_list($post_type);
			$selected = reset(array_intersect((array) @$connections[$post_type], array_keys($posts)));

			$out .= 
			html('li', 
				 get_post_type_object($post_type)->labels->singular_name . ' '
				.scbForms::input(array(
					'type' => 'select',
					'name' => "p2p[$post_type]",
					'values' => self::get_post_list($post_type),
					'selected' => $selected,
				))
			);
		}

		echo html('ul', $out);
	}

	private function get_post_list($post_type) {
		$args = array(
			'post_type' => $post_type,
			'post_status' => 'any',
			'nopaging' => true,
			'cache_results' => false,
		);

		return scbUtil::objects_to_assoc(get_posts($args), 'ID', 'post_title');
	}
}

