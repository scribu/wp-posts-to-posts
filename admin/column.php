<?php

abstract class P2P_Column {

	protected $ctype;

	protected $connected = array();

	function __construct( $directed, $items ) {
		$this->ctype = $directed;

		$extra_qv = array( 'p2p:context' => 'admin_column' );

		$connected = $this->ctype->get_connected( $items, $extra_qv, 'abstract' );

		$this->connected = p2p_triage_connected( $connected->items );
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

	abstract function get_admin_link( $item );

	protected function render_column( $column, $item_id ) {
		if ( $this->ctype->name != $column )
			return;

		$side = $this->ctype->get_opposite( 'side' );

		if ( !isset( $this->connected[ $item_id ] ) )
			return;

		$out = '<ul>';
		foreach ( $this->connected[ $item_id ] as $item ) {
			$out .= html( 'li', html_link( $this->get_admin_link( $item ), $side->item_title( $item ) ) );
		}
		$out .= '</ul>';

		return $out;
	}
}


class P2P_Column_Post extends P2P_Column {

	function __construct( $directed ) {
		global $wp_query;

		$this->ctype = $directed;

		$extra_qv = array( 'p2p:context' => 'admin_column' );

		parent::__construct( $directed, $wp_query->posts );

		$screen = get_current_screen();

		add_action( "manage_{$screen->post_type}_posts_custom_column", array( $this, 'display_column' ), 10, 2 );
	}

	function get_admin_link( $item ) {
		$args = array(
			'connected_type' => $this->ctype->name,
			'connected_direction' => $this->ctype->flip_direction()->get_direction(),
			'connected_items' => $item->ID,
			'post_type' => get_current_screen()->post_type
		);

		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	function display_column( $column, $item_id ) {
		echo parent::render_column( $column, $item_id );
	}
}


class P2P_Column_User extends P2P_Column {

	function __construct( $directed ) {
		global $wp_list_table;

		parent::__construct( $directed, $wp_list_table->items );

		add_filter( 'manage_users_custom_column', array( $this, 'display_column' ), 10, 3 );
	}

	function get_admin_link( $item ) {
		$args = array(
			'connected_type' => $this->ctype->name,
			'connected_direction' => $this->ctype->flip_direction()->get_direction(),
			'connected_items' => $item->ID,
		);

		return add_query_arg( $args, admin_url( 'users.php' ) );
	}

	function display_column( $content, $column, $item_id ) {
		return parent::render_column( $column, $item_id );
	}
}

