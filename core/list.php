<?php

abstract class P2P_List {

	public $items;
	public $current_page = 1;
	public $total_pages = 0;

	function __construct( $items ) {
		if ( is_numeric( reset( $items ) ) ) {
			// Don't wrap when we just have a list of ids
			$this->items = $items;
		} else {
			$class = str_replace( 'P2P_List', 'P2P_Item', get_class( $this ) );
			$this->items = _p2p_wrap( $items, $class );
		}
	}

	function render( $args = array() ) {
		if ( empty( $this->items ) )
			return '';

		$args = wp_parse_args( $args, array(
			'before_list' => '<ul>', 'after_list' => '</ul>',
			'before_item' => '<li>', 'after_item' => '</li>',
			'separator' => false,
			'echo' => true
		) );

		extract( $args, EXTR_SKIP );

		if ( $separator ) {
			if ( '<ul>' == $before_list )
				$before_list = '';

			if ( '</ul>' == $after_list )
				$after_list = '';
		}

		if ( !$echo )
			ob_start();

		echo $before_list;

		if ( $separator ) {
			$list = array();
			foreach ( $this->items as $item ) {
				$list[] = $this->render_item( $item->get_object() );
			}
			echo implode( $separator, $list );
		} else {
			foreach ( $this->items as $item ) {
				echo $before_item . $this->render_item( $item ) . $after_item;
			}
		}

		echo $after_list;

		if ( !$echo )
			return ob_get_clean();
	}

	protected function render_item( $item ) {
		return html_link( $item->get_permalink(), $item->get_title() );
	}
}


class P2P_List_Post extends P2P_List {

	function __construct( $wp_query ) {
		if ( is_array( $wp_query ) ) {
			$items = $wp_query;
		} else {
			$items = $wp_query->posts;
			$this->current_page = max( 1, $wp_query->get('paged') );
			$this->total_pages = $wp_query->max_num_pages;
		}

		parent::__construct( $items );
	}
}


class P2P_List_Attachment extends P2P_List_Post {}


class P2P_List_User extends P2P_List {

	function __construct( $query ) {
		$qv = $query->query_vars;

		if ( isset( $qv['p2p:page'] ) ) {
			$this->current_page = $qv['p2p:page'];
			$this->total_pages = ceil( $query->get_total() / $qv['p2p:per_page'] );
		}

		parent::__construct( $query->get_results() );
	}
}

