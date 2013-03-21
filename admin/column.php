<?php

abstract class P2P_Column {

	protected $ctype;

	protected $connected = array();

	function __construct( $directed ) {
		$this->ctype = $directed;

		$this->column_id = sprintf( 'p2p-%s-%s',
			$this->ctype->get_direction(),
			$this->ctype->name
		);

		$screen = get_current_screen();

		add_filter( "manage_{$screen->id}_columns", array( $this, 'add_column' ) );
	}

	function add_column( $columns ) {
		$this->prepare_items();

		$labels = $this->ctype->get( 'current', 'labels' );

		$title = isset( $labels->column_title )
			? $labels->column_title
			: $this->ctype->get( 'current', 'title' );

		return array_splice( $columns, 0, -1 ) + array( $this->column_id => $title ) + $columns;
	}

	protected abstract function get_items();

	protected function prepare_items() {
		$items = $this->get_items();

		$extra_qv = array(
			'p2p:per_page' => -1,
			'p2p:context' => 'admin_column'
		);

		$connected = $this->ctype->get_connected( $items, $extra_qv, 'abstract' );

		$this->connected = scb_list_group_by( $connected->items, '_p2p_get_other_id' );
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

