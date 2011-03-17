<?php

class P2P_Box_Multiple extends P2P_Box {

	function setup() {
		$ptype_obj = get_post_type_object( $this->to );

		$this->columns = array_merge(
			array( 'post' => $ptype_obj->labels->singular_name ),
			$this->fields,
			array( 'delete' => '' )
		);
	}

	function connect() {
		$from = absint( $_POST['from'] );
		$to = absint( $_POST['to'] );

		if ( !$from || !$to )
			die(-1);

		$args = array( $from, $to );

		if ( $this->reversed )
			$args = array_reverse( $args );

		$p2p_id = false;
		if ( $this->prevent_duplicates ) {
			$p2p_ids = P2P_Connections::get( $args[0], $args[1] );

			if ( !empty( $p2p_ids ) )
				$p2p_id = $p2p_ids[0];
		}

		if ( !$p2p_id )
			$p2p_id = P2P_Connections::connect( $args[0], $args[1] );

		$this->display_row( $p2p_id, $to );
	}

	function disconnect() {
		$p2p_id = absint( $_POST['p2p_id'] );

		p2p_delete_connection( $p2p_id );

		die(1);
	}

	function box( $post_id ) {
		$connected_ids = $this->get_connected_ids( $post_id );

		$data_attr = array();
		foreach ( array( 'box_id', 'direction', 'prevent_duplicates' ) as $key )
			$data_attr[] = "data-$key='" . $this->$key . "'";
		$data_attr = implode( ' ', $data_attr );

?>
<table class="p2p-connections">
	<thead>
		<tr>
		<?php foreach ( $this->columns as $key => $title ) {
			echo html( 'th', array( 'class' => "p2p-col-$key" ), $title );
		} ?>
		</tr>
	</thead>

	<tbody>
	<?php foreach ( $connected_ids as $p2p_id => $post_b ) {
		$this->display_row( $p2p_id, $post_b );
	} ?>
	</tbody>
</table>

<div class="p2p-add-new" <?php echo $data_attr; ?>>
		<p><strong><?php _e( 'Add New Connection:', 'posts-to-posts' ); ?></strong></p>

		<p class="p2p-search">
			<?php _e( 'Search:', 'posts-to-posts' ); ?>
			<?php echo html( 'input', array(
				'type' => 'text',
				'name' => 'p2p_search_' . $this->to,
				'autocomplete' => 'off',
			) ); ?>
			<img alt="" src="<?php echo admin_url( 'images/wpspin_light.gif' ); ?>" class="waiting" style="display: none;">
		</p>

		<ul class="p2p-results"></ul>
</div>
<?php
	}

	protected function display_row( $p2p_id, $post_id ) {
		echo '<tr data-p2p-id="' . $p2p_id . '">';

		foreach ( array_keys( $this->columns ) as $key ) {
			switch ( $key ) {
				case 'post':
					$value = html( 'a', array(
						'href' => str_replace( '&amp;', '&', get_edit_post_link( $post_id ) ),
						'title' => __( 'Edit', 'posts-to-posts' )
					), get_post_field( 'post_title', $post_id ) );
					break;

				case 'delete':
					$value = html( 'a', array(
						'href' => '#',
						'title' => __( 'Delete', 'posts-to-posts' )
					), __( 'Delete', 'posts-to-posts' ) );
					break;

				default:
					$value = html( 'input', array(
						'type' => 'text',
						'name' => "p2p_meta[$p2p_id][$key]",
						'value' => p2p_get_meta( $p2p_id, $key, true )
					) );
			}

			echo html( 'td', array( 'class' => "p2p-col-$key" ), $value );
		}

		echo '</tr>';
	}

	protected function get_connected_ids( $post_id ) {
		$connected_posts = p2p_get_connected( $post_id, $this->direction );

		if ( empty( $connected_posts ) )
			return array();

		$args = array(
			'post__in' => $connected_posts,
			'post_type'=> $this->to,
			'post_status' => 'any',
			'nopaging' => true,
			'suppress_filters' => false,
		);

		$post_ids = scbUtil::array_pluck( get_posts($args), 'ID' );

		return array_intersect( $connected_posts, $post_ids );	// to preserve p2p_id keys
	}

	function get_search_args( $search, $post_id ) {
		$args = array(
			's' => $search,
			'post_type' => $this->to,
			'post_status' => 'any',
			'posts_per_page' => 5,
			'order' => 'ASC',
			'orderby' => 'title',
			'suppress_filters' => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false
		);

		if ( $this->prevent_duplicates )
			$args['post__not_in'] = p2p_get_connected( $post_id, $this->direction );

		return $args;
	}
}

