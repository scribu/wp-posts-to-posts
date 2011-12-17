<?php

class P2P_Column {

	protected $ctype;

	protected $connected = array();

	function __construct( $directed ) {
		$this->ctype = $directed;

		$this->ctype->lose_direction()->each_connected( $GLOBALS['wp_query'] );

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
	margin-top: -17px;
	margin-bottom: 0;
}
</style>
<?php
	}

	function display_column( $column, $post_id ) {
		if ( $this->ctype->name != $column )
			return;

		$opposite_direction = array(
			'from' => 'to',
			'to' => 'from',
			'any' => 'any'
		);

		echo '<ul>';
		foreach ( $this->connected[ $post_id ] as $post ) {
			$direction = $opposite_direction[ $this->ctype->get_direction() ];

			$args = array(
				'post_type' => get_post_type( $post_id ),
				'connected_type' => $this->ctype->name,
				'connected_items' => $post->ID,
				'connected_direction' => $direction
			);

			$url = add_query_arg( $args, admin_url( 'edit.php' ) );

			echo html( 'li', html_link( $url, $post->post_title ) );
		}
		echo '</ul>';
	}
}

