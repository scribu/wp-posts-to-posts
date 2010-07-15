<?php

//
// Template tags are helper functions for displaying connections
//

/**
 * Display the list of connected posts
 *
 * @param string $post_type The post type of the connected posts.
 * @param string $direction The direction of the connection. Can be 'to' or 'from'
 * @param int $post_id One end of the connection
 * @param callback(WP_Query) $callback the function used to do the actual displaying
 */
function p2p_list_connected( $post_type = 'any', $direction = 'from', $post_id = '', $callback = '' ) {
	if ( !$post_id )
		$post_id = get_the_ID();

	$connected_post_ids = p2p_get_connected( $post_type, $direction, $post_id );

	if ( empty( $connected_post_ids ) )
		return;

	$args = array(
		'post__in' => $connected_post_ids,
		'post_type'=> $post_type,
		'nopaging' => true,
	);
	$query = new WP_Query( $args );

	if ( empty( $callback ) )
		$callback = '_p2p_list_connected';

	call_user_func( $callback, $query );

	wp_reset_postdata();
}

/**
 * The default callback for p2p_list_connected()
 * Lists the posts as an unordered list
 *
 * @param WP_Query
 */
function _p2p_list_connected( $query ) {
	if ( $query->have_posts() ) :
		echo '<ul>';
		while ( $query->have_posts() ) : $query->the_post();
			echo html( 'li', html_link( get_permalink( get_the_ID() ), get_the_title() ) );
		endwhile;
		echo '</ul>';
	endif;
}

