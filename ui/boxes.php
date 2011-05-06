<?php

class P2P_Box_Multiple extends P2P_Box {

	function setup() {
		$this->columns = array_merge(
			array( 'delete' => $this->clear_connections_link() ),
			array( 'title' => get_post_type_object( $this->to )->labels->singular_name ),
			$this->fields
		);
	}

	function create_post() {
		$new_post_id = wp_insert_post( array(
			'post_title' => $_POST['post_title'],
			'post_author' => 1,
			'post_type' => $this->to
		) );

		$this->safe_connect( absint( $_POST['from'] ), $new_post_id );
	}

	function connect() {
		$this->safe_connect( absint( $_POST['from'] ), absint( $_POST['to'] ) );
	}

	protected function safe_connect( $from, $to ) {
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

		$this->connection_row( $p2p_id, $to );
	}

	function disconnect() {
		$p2p_id = absint( $_POST['p2p_id'] );

		p2p_delete_connection( $p2p_id );

		die(1);
	}

	function clear_connections() {
		$post_id = absint( $_POST['post_id'] );

		p2p_disconnect( $post_id, $this->direction );

		die(1);
	}

	function box( $post_id ) {
		$connected_ids = $this->get_connected_ids( $post_id );

		$data_attr = array();
		foreach ( array( 'box_id', 'direction', 'prevent_duplicates' ) as $key )
			$data_attr[] = "data-$key='" . $this->$key . "'";
		$data_attr = implode( ' ', $data_attr );

		$to_cpt = get_post_type_object( $this->to );
?>

<div class="p2p-box">
	<table class="p2p-connections" <?php if ( empty( $connected_ids ) ) echo 'style="display:none"'; ?>>
		<thead>
			<tr>
				<?php foreach ( $this->columns as $key => $title ) {
					echo html( 'th', array( 'class' => "p2p-col-$key" ), $title );
				} ?>
			</tr>
		</thead>

		<tbody>
			<?php foreach ( $connected_ids as $p2p_id => $post_b ) {
				$this->connection_row( $p2p_id, $post_b );
			} ?>
		</tbody>
	</table>

	<div class="p2p-add-new" <?php echo $data_attr; ?>>
		<p><strong><?php _e( 'Create connections:', 'posts-to-posts' ); ?></strong></p>

		<div class="p2p-search">
			<?php echo html( 'input', array(
				'type' => 'text',
				'name' => 'p2p_search_' . $this->to,
				'autocomplete' => 'off',
				'placeholder' => $to_cpt->labels->search_items
			) ); ?>
		</div>

		<table class="p2p-results">
			<tbody>
			</tbody>
		</table>

<?php if ( current_user_can( $to_cpt->cap->edit_posts ) ) { ?>
                <div class="p2p-topost-adder">
			<h4><a class="p2p-topost-adder-toggle" href="#">+ <?php echo $to_cpt->labels->add_new_item; ?></a></h4>
			<div class="p2p-title-to-post">
				<?php echo html( 'input', array(
					'type' => 'text',
					'name' => 'p2p_new_title_' . $this->to,
					'autocomplete' => 'off',
				) ); ?>
				<input type="button" class="p2p-create-post button" value="<?php esc_attr_e( 'Add', 'posts-to-posts' ); ?>" />
			</div>
		</div>
<?php } ?>
	</div><!--.p2p-add-new-->
</div><!--.p2p-box-->

<div class="p2p-footer">
	<div class="p2p-nav">
		<div class="p2p-prev button" title="<?php _e( 'Previous', 'p2p-textdomain' ); ?>">&lsaquo;</div>
		<div><span class="p2p-current"></span> <? _e( 'of', 'p2p-textdomain' ); ?> <span class="p2p-total"></span></div>
		<div class="p2p-next button" title="<?php _e( 'Next', 'p2p-textdomain' ); ?>">&rsaquo;</div>
	</div>
	<input type="button" class="p2p-recent button" value="<?php esc_attr_e( 'Recent', 'posts-to-posts' ); ?>" />

	<div class="clear">
		<!-- Clearfix would be better -->
	</div>
</div>


<?php
	}

	protected function connection_row( $p2p_id, $post_id ) {
		echo '<tr>';

	foreach ( array_keys( $this->columns ) as $key ) {
			switch ( $key ) {
				case 'title':
					$value = $this->column_title( $post_id );
					break;

				case 'delete':
					$value = $this->column_delete( $p2p_id );
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

	public function results_row( $post ) {
		echo '<tr>';

		foreach ( array( 'add', 'title' ) as $key ) {
			$method = "column_$key";
			echo html( 'td', array( 'class' => "p2p-col-$key" ), $this->$method( $post->ID ) );
		}

		echo '</tr>';
	}

	protected function column_title( $post_id ) {
		$post_status = get_post_status( $post_id );

		$status_text = '';
		if ( 'publish' != $post_status ) {
			$status_obj = get_post_status_object( $post_status );
			if ( $status_obj ) {
				$status_text = $status_obj->label;
			}
		}

		if ( ! empty( $status_text ) )
			$status_text = html( 'span', array( 'class' => 'post-state' ), ' - ', $status_text );

		return html( 'a', array(
			'href' => str_replace( '&amp;', '&', get_edit_post_link( $post_id ) ),
			'title' => get_post_type_object( get_post_type( $post_id ) )->labels->edit_item,
		), get_post_field( 'post_title', $post_id ) ) . $status_text;
	}

	protected function column_add( $post_id ) {
		return html( 'a', array(
			'data-post_id' => $post_id,
			'href' => '#',
			'title' => __( 'Create connection', 'posts-to-posts' )
		), __( 'Create connection', 'posts-to-posts' ) );
	}

	protected function column_delete( $p2p_id ) {
		return html( 'a', array(
			'data-p2p_id' => $p2p_id,
			'href' => '#',
			'title' => __( 'Delete connection', 'posts-to-posts' )
		), __( 'Delete connection', 'posts-to-posts' ) );
	}

	protected function clear_connections_link() {
		return html( 'a', array(
			'href' => '#',
			'title' => __( 'Delete all connections', 'posts-to-posts' )
		), __( 'Delete all connections', 'posts-to-posts' ) );
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

	function get_search_args( $args, $post_id ) {
		$args = array_merge( $args, array(
			'post_type' => $this->to,
			'post_status' => 'any',
			'posts_per_page' => 5,
			'suppress_filters' => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false
		) );

		if ( $this->prevent_duplicates )
			$args['post__not_in'] = p2p_get_connected( $post_id, $this->direction );

		return $args;
	}
	
	function get_recent_args( $post_id ) {
		$args = array(
			'numberposts' => 10,
			'orderby' => 'post_date',
			'order' => 'DESC',
			'post_type' => $this->to,
			'post_status' => 'publish',
			'suppress_filters' => false //true
		);

		if ( $this->prevent_duplicates )
			$args['post__not_in'] = p2p_get_connected( $post_id, $this->direction );

		return $args;
	}
}

