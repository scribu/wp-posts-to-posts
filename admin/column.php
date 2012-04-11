<?php

class P2P_Column {

	protected $ctype;

	protected $connected = array();

	function __construct( $directed ) {
		$this->ctype = $directed;

		$extra_qv = array( 'p2p:context' => 'admin_column' );

		$this->ctype->lose_direction()->each_connected( $GLOBALS['wp_query'], $extra_qv );

		$this->connected = scb_list_fold( $GLOBALS['wp_query']->posts, 'ID', 'connected' );
	}

	function add_column( $columns ) {
		$columns[ $this->ctype->name ] = $this->ctype->get_current( 'title' );

		return $columns;
	}

	function styles() {
?>
<style type="text/css">
.column-<?php echo $this->ctype->name; ?> ul {
	margin-top: 0;
	margin-bottom: 0;
}
</style>
<?php
	}

	function display_column( $column, $item_id ) {
		if ( $this->ctype->name != $column )
			return;

		$opposite_direction = array(
			'from' => 'to',
			'to' => 'from',
			'any' => 'any'
		);

		$direction = $opposite_direction[ $this->ctype->get_direction() ];

		$side = $this->ctype->side[ $direction ];

		echo '<ul>';
		foreach ( $this->connected[ $item_id ] as $item ) {
			$args = array(
				'connected_type' => $this->ctype->name,
				'connected_direction' => $direction,
				'connected_items' => $item->ID,
				'post_type' => get_current_screen()->post_type
			);

			$url = add_query_arg( $args, admin_url( 'edit.php' ) );

			echo html( 'li', html_link( $url, $side->item_title( $item ) ) );
		}
		echo '</ul>';
	}
}

