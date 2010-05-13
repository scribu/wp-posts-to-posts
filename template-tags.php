<?php

function p2p_list_connected($post_type, $direction, $post_id = '', $callback = '') {
	if ( empty($post_type) )
		$post_type = 'any';

	if ( !$post_id )
		$post_id = get_the_ID();

	$connected_post_ids = p2p_get_connected($post_type, $direction, $post_id);

	if ( empty($connected_post_ids) )
		return;

	$args = array(
		'post__in' => $connected_post_ids, 
		'post_type'=> $post_type,
		'nopaging' => true,
	);
	$query = new WP_Query($args);

	if ( empty($callback) )
		$callback = '_p2p_list_connected';

	$callback($query);

	wp_reset_postdata();
}

function _p2p_list_connected($query) {
	if ( $query->have_posts() ) :
		echo '<ul>';
		while ( $query->have_posts() ) : $query->the_post();
			echo html('li', html_link(get_permalink(get_the_ID()), get_the_title()));
		endwhile;
		echo '</ul>';
	endif;
}

if ( !function_exists('wp_reset_postdata') ) :
function wp_reset_postdata() {
	global $wp_query;
	if ( !empty($wp_query->post) ) {
		$GLOBALS['post'] = $wp_query->post;
		setup_postdata($wp_query->post);
	}
}
endif;
