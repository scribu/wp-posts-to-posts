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
			: $labels->title;

		$columns[ $this->column_id ] = $title;

		return $columns;
	}

	protected abstract function get_items();

	protected function prepare_items() {
		$items = $this->get_items();

		$extra_qv = array(
			'p2p:per_page' => -1,
			'p2p:context' => 'admin_column'
		);

		$connected = $this->ctype->get_connected( $items, $extra_qv, 'abstract' );

		$this->connected = p2p_list_cluster( $connected->items, '_p2p_get_other_id' );
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
		parent::__construct( $directed );

		$screen = get_current_screen();

		add_action( "manage_{$screen->post_type}_posts_custom_column", array( $this, 'display_column' ), 10, 2 );
	}

	protected function get_items() {
		global $wp_query;

		return $wp_query->posts;
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
		parent::__construct( $directed );

		add_action( 'pre_user_query', array( __CLASS__, 'user_query' ), 9 );

		add_filter( 'manage_users_custom_column', array( $this, 'display_column' ), 10, 3 );
	}

	protected function get_items() {
		global $wp_list_table;

		return $wp_list_table->items;
	}

	// Add the query vars to the global user query (on the user admin screen)
	static function user_query( $query ) {
		if ( isset( $query->_p2p_capture ) )
			return;

		// Don't overwrite existing P2P query
		if ( isset( $query->query_vars['connected_type'] ) )
			return;

		_p2p_append( $query->query_vars, wp_array_slice_assoc( $_GET,
			P2P_URL_Query::get_custom_qv() ) );
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

