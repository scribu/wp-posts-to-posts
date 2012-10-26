<?php

abstract class P2P_Column {

	protected $ctype;

	protected $connected = array();

	function __construct( $directed, $items ) {
		$this->ctype = $directed;
		$this->column_id = sprintf( 'p2p-%s-%s',
			$this->ctype->get_direction(),
			$this->ctype->name
		);

		$extra_qv = array(
			'p2p:per_page' => -1,
			'p2p:context' => 'admin_column'
		);

		$connected = $this->ctype->get_connected( $items, $extra_qv, 'abstract' );

		$this->connected = p2p_triage_connected( $connected->items );

		$screen = get_current_screen();

		add_filter( "manage_{$screen->id}_columns", array( $this, 'add_column' ) );
	}

	function add_column( $columns ) {

		$columns[ $this->column_id ] = $this->ctype->get( 'current', 'title' );

		return $columns;
	}

	function styles() {
?>
<style type="text/css">
.column-<?php echo $this->column_id; ?> ul {
	margin-top: 0;
	margin-bottom: 0;
}
</style>
<?php
	}

	abstract function get_admin_link( $item );

	protected function render_column( $column, $item_id ) {
		if ( $this->column_id != $column )
			return;

		if ( !isset( $this->connected[ $item_id ] ) )
			return;

		$out = '<ul>';
		foreach ( $this->connected[ $item_id ] as $item ) {
			$out .= html( 'li', html_link( $this->get_admin_link( $item ), $item->get_title() ) );
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
			'connected_items' => $item->get_id(),
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
			'connected_items' => $item->get_id(),
		);

		return add_query_arg( $args, admin_url( 'users.php' ) );
	}

	function display_column( $content, $column, $item_id ) {
		return parent::render_column( $column, $item_id );
	}
}

