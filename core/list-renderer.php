<?php

class P2P_List_Renderer {

	static function query_and_render( $args ) {
		$ctype = p2p_type( $args['ctype'] );
		if ( !$ctype ) {
			trigger_error( sprintf( "Unregistered connection type '%s'.", $ctype ), E_USER_WARNING );
			return '';
		}

		$directed = $ctype->find_direction( $args['item'] );
		if ( !$directed )
			return '';

		$context = $args['context'];

		$extra_qv = array(
			'p2p:per_page' => -1,
			'p2p:context' => $context
		);

		$connected = call_user_func( array( $directed, $args['method'] ), $args['item'], $extra_qv, 'abstract' );

		switch ( $args['mode'] ) {
		case 'inline':
			$render_args = array(
				'separator' => ', '
			);
			break;

		case 'ol':
			$render_args = array(
				'before_list' => '<ol id="' . $ctype->name . '_list">',
				'after_list' => '</ol>',
			);
			break;

		case 'ul':
		default:
			$render_args = array(
				'before_list' => '<ul id="' . $ctype->name . '_list">',
				'after_list' => '</ul>',
			);
			break;
		}

		$render_args['echo'] = false;

		$html = self::render( $connected, $render_args );

		return apply_filters( "p2p_{$context}_html", $html, $connected, $directed, $args['mode'] );
	}

	static function render( $list, $args = array() ) {
		if ( empty( $list->items ) )
			return '';

		$args = wp_parse_args( $args, array(
			'before_list' => '<ul>', 'after_list' => '</ul>',
			'before_item' => '<li>', 'after_item' => '</li>',
			'separator' => false,
			'echo' => true
		) );

		if ( $args['separator'] ) {
			if ( '<ul>' == $args['before_list'] )
				$args['before_list'] = '';

			if ( '</ul>' == $args['after_list'] )
				$args['after_list'] = '';
		}

		if ( !$args['echo'] )
			ob_start();

		echo $args['before_list'];

		if ( $args['separator'] ) {
			$rendered = array();
			foreach ( $list->items as $item ) {
				$rendered[] = self::render_item( $item );
			}
			echo implode( $args['separator'], $rendered );
		} else {
			foreach ( $list->items as $item ) {
				echo $args['before_item'] . self::render_item( $item ) . $args['after_item'];
			}
		}

		echo $args['after_list'];

		if ( !$args['echo'] )
			return ob_get_clean();
	}

	private static function render_item( $item ) {
		return html_link( $item->get_permalink(), $item->get_title() );
	}
}

