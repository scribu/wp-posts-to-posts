<?php
/**
 * Class that creates metaboxes on the post editing page.
 */
class scbPostMetabox {

	/**
	 * Metabox ID.
	 * @var string
	 */
	private $id;

	/**
	 * Title.
	 * @var string
	 */
	private $title;

	/**
	 * Post types.
	 * @var array
	 */
	private $post_types;

	/**
	 * Post meta data.
	 * @var array
	 */
	private $post_data = array();

	/**
	 * Action hooks.
	 * @var array
	 */
	protected $actions = array( 'admin_enqueue_scripts', 'post_updated_messages' );

	/**
	 * Sets up metabox.
	 *
	 * @param string $id
	 * @param string $title
	 * @param array $args (optional)
	 *
	 * @return void
	 */
	public function __construct( $id, $title, $args = array() ) {
		$this->id = $id;
		$this->title = $title;

		$args = wp_parse_args( $args, array(
			'post_type' => 'post',
			'context' => 'advanced',
			'priority' => 'default',
		) );

		if ( is_string( $args['post_type'] ) ) {
			$args['post_type'] = array( $args['post_type'] );
		}

		$this->post_types = $args['post_type'];
		$this->context = $args['context'];
		$this->priority = $args['priority'];

		add_action( 'load-post.php', array( $this, 'pre_register' ) );
		add_action( 'load-post-new.php', array( $this, 'pre_register' ) );
	}

	/**
	 * Pre register the metabox.
	 *
	 * @return void
	 */
	final public function pre_register() {
		if ( ! in_array( get_current_screen()->post_type, $this->post_types ) ) {
			return;
		}

		if ( ! $this->condition() ) {
			return;
		}

		if ( isset( $_GET['post'] ) ) {
			$this->post_data = $this->get_meta( intval( $_GET['post'] ) );
		}

		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post', array( $this, '_save_post' ), 10, 2 );

		foreach ( $this->actions as $action ) {
			if ( method_exists( $this, $action ) ) {
				add_action( $action, array( $this, $action ) );
			}
		}
	}

	/**
	 * Additional checks before registering the metabox.
	 *
	 * @return bool
	 */
	protected function condition() {
		return true;
	}

	/**
	 * Registers the metabox.
	 *
	 * @return void
	 */
	final public function register() {
		add_meta_box( $this->id, $this->title, array( $this, 'display' ), null, $this->context, $this->priority );
	}

	/**
	 * Filter data before display.
	 *
	 * @param array $form_data
	 * @param object $post
	 *
	 * @return array
	 */
	public function before_display( $form_data, $post ) {
		return $form_data;
	}

	/**
	 * Displays metabox content.
	 *
	 * @param object $post
	 *
	 * @return void
	 */
	public function display( $post ) {
		$form_fields = $this->form_fields();
		if ( ! $form_fields ) {
			return;
		}

		$form_data = $this->post_data;
		$error_fields = array();

		if ( isset( $form_data['_error_data_' . $this->id ] ) ) {
			$data = maybe_unserialize( $form_data['_error_data_' . $this->id ] );

			$error_fields = $data['fields'];
			$form_data = $data['data'];

			$this->display_notices( $data['messages'], 'error' );
		}

		$form_data = $this->before_display( $form_data, $post );

		$this->before_form( $post );
		echo $this->table( $form_fields, $form_data, $error_fields );
		$this->after_form( $post );

		delete_post_meta( $post->ID, '_error_data_' . $this->id );
	}

	/**
	 * Returns table.
	 *
	 * @param array $rows
	 * @param array $formdata
	 * @param array $errors (optional)
	 *
	 * @return string
	 */
	public function table( $rows, $formdata, $errors = array() ) {
		$output = '';
		foreach ( $rows as $row ) {
			$output .= $this->table_row( $row, $formdata, $errors );
		}

		$output = scbForms::table_wrap( $output );

		return $output;
	}

	/**
	 * Returns table row.
	 *
	 * @param array $row
	 * @param array $formdata
	 * @param array $errors (optional)
	 *
	 * @return string
	 */
	public function table_row( $row, $formdata, $errors = array() ) {
		$input = scbForms::input( $row, $formdata );

		// If row has an error, highlight it
		$style = ( in_array( $row['name'], $errors ) ) ? 'style="background-color: #FFCCCC"' : '';

		return html( 'tr',
			html( "th $style scope='row'", $row['title'] ),
			html( "td $style", $input )
		);
	}

	/**
	 * Displays notices.
	 *
	 * @param array|string $notices
	 * @param string $class (optional)
	 *
	 * @return void
	 */
	public function display_notices( $notices, $class = 'updated' ) {
		// add inline class so the notices stays in metabox
		$class .= ' inline';

		foreach ( (array) $notices as $notice ) {
			echo scb_admin_notice( $notice, $class );
		}
	}

	/**
	 * Display some extra HTML before the form.
	 *
	 * @param object $post
	 *
	 * @return void
	 */
	public function before_form( $post ) { }

	/**
	 * Return an array of form fields.
	 *
	 * @return array
	 */
	public function form_fields() {
		return array();
	}

	/**
	 * Display some extra HTML after the form.
	 *
	 * @param object $post
	 *
	 * @return void
	 */
	public function after_form( $post ) { }

	/**
	 * Makes sure that the saving occurs only for the post being edited.
	 *
	 * @param int $post_id
	 * @param object $post
	 *
	 * @return void
	 */
	final public function _save_post( $post_id, $post ) {
		if ( ! isset( $_POST['action'] ) || $_POST['action'] != 'editpost' ) {
			return;
		}

		if ( ! isset( $_POST['post_ID'] ) || $_POST['post_ID'] != $post_id ) {
			return;
		}

		if ( ! in_array( $post->post_type, $this->post_types ) ) {
			return;
		}

		$this->save( $post->ID );
	}

	/**
	 * Saves metabox form data.
	 *
	 * @param int $post_id
	 *
	 * @return void
	 */
	protected function save( $post_id ) {
		$form_fields = $this->form_fields();

		$to_update = scbForms::validate_post_data( $form_fields );

		// Filter data
		$to_update = $this->before_save( $to_update, $post_id );

		// Validate dataset
		$is_valid = $this->validate_post_data( $to_update, $post_id );
		if ( $is_valid instanceof WP_Error && $is_valid->get_error_codes() ) {

			$error_data = array(
				'fields' => $is_valid->get_error_codes(),
				'messages' => $is_valid->get_error_messages(),
				'data' => $to_update,
			);
			update_post_meta( $post_id, '_error_data_' . $this->id, $error_data );

			$location = add_query_arg( 'message', 1, get_edit_post_link( $post_id, 'url' ) );
			wp_redirect( esc_url_raw( apply_filters( 'redirect_post_location', $location, $post_id ) ) );
			exit;
		}

		foreach ( $to_update as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Filter data before save.
	 *
	 * @param array $post_data
	 * @param int $post_id
	 *
	 * @return array
	 */
	protected function before_save( $post_data, $post_id ) {
		return $post_data;
	}

	/**
	 * Validate posted data.
	 *
	 * @param array $post_data
	 * @param int $post_id
	 *
	 * @return bool|object A WP_Error object if posted data are invalid.
	 */
	protected function validate_post_data( $post_data, $post_id ) {
		return false;
	}

	/**
	 * Returns an array of post meta.
	 *
	 * @param int $post_id
	 *
	 * @return array
	 */
	private function get_meta( $post_id ) {
		$meta = get_post_custom( $post_id );
		foreach ( $meta as $key => $values ) {
			$meta[ $key ] = maybe_unserialize( $meta[ $key ][0] );
		}

		return $meta;
	}

}

