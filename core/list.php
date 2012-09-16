<?php

class P2P_List {

	public $items;
	public $current_page = 1;
	public $total_pages = 0;

	function __construct( $items, $item_type ) {
		if ( is_numeric( reset( $items ) ) ) {
			// Don't wrap when we just have a list of ids
			$this->items = $items;
		} else {
			$this->items = _p2p_wrap( $items, $item_type );
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
				$list[] = $this->render_item( $item );
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

