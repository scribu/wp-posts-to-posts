<?php

abstract class P2P_List {

	public $items;
	public $current_page = 1;
	public $total_pages = 0;

	function render( $args = array() ) {
		if ( empty( $this->items ) )
			return '';

		$args = wp_parse_args( $args, array(
			'before_list' => '<ul>', 'after_list' => '</ul>',
			'before_item' => '<li>', 'after_item' => '</li>',
			'separator' => false,
			'template' => false,
			'echo' => true
		) );

		extract( $args, EXTR_SKIP );

		if ( !$echo )
			ob_start();

		echo $before_list;

		$i = 0;

		foreach ( $this->items as $item ) {
			if ( !$separator ) echo $before_item;

			if ( !$template || !locate_template( $template, true, false ) ) {
				if ( 0 < $i && $separator ) echo $separator;

				echo $this->render_item( $item );
			}

			if ( !$separator ) echo $after_item;

			$i++;
		}

		echo $after_list;

		if ( !$echo )
			return ob_get_clean();
	}

	abstract protected function render_item( $item );
}


class P2P_List_Post extends P2P_List {

	function __construct( $wp_query ) {
		if ( is_array( $wp_query ) ) {
			$this->items = $wp_query;
		} else {
			$this->items = $wp_query->posts;
			$this->current_page = max( 1, $wp_query->get('paged') );
			$this->total_pages = $wp_query->max_num_pages;
		}
	}

	function render( $args = array() ) {
		$r = parent::render( $args );

		wp_reset_postdata();

		return $r;
	}

	protected function render_item( $post ) {
		$GLOBALS['post'] = $post;

		setup_postdata( $post );

		return html( 'a', array( 'href' => get_permalink() ), get_the_title() );
	}
}


class P2P_List_Attachment extends P2P_List_Post {

	protected function render_item( $post ) {
		$GLOBALS['post'] = $post;

		setup_postdata( $post );

		return html( 'a', array( 'href' => get_permalink() ), wp_get_attachment_image( $post->ID, 'thumbnail', false ) );
	}
}


class P2P_List_User extends P2P_List {

	function __construct( $query ) {
		$qv = $query->query_vars;

		$this->items = $query->get_results();

		if ( isset( $qv['p2p:page'] ) ) {
			$this->current_page = $qv['p2p:page'];
			$this->total_pages = ceil( $query->get_total() / $qv['p2p:per_page'] );
		}
	}

	protected function render_item( $user ) {
		return html( 'a', array( 'href' => get_author_posts_url( $user->ID ) ), $user->display_name );
	}
}

