<?php
/**
 * Admin screen with metaboxes base class.
 */
abstract class scbBoxesPage extends scbAdminPage {
	/*
		A box definition looks like this:
		array( $slug, $title, $column );

		Available columns: normal, side, column3, column4
	*/
	protected $boxes = array();

	/**
	 * Constructor.
	 *
	 * @param string|bool $file (optional)
	 * @param object $options (optional) A scbOptions object.
	 *
	 * @return void
	 */
	public function __construct( $file = false, $options = null ) {
		parent::__construct( $file, $options );

		scbUtil::add_uninstall_hook( $this->file, array( $this, 'uninstall' ) );
	}

	/**
	 * Registers a page.
	 *
	 * @return void
	 */
	public function page_init() {
		if ( ! isset( $this->args['columns'] ) ) {
			$this->args['columns'] = 2;
		}

		parent::page_init();

		add_action( 'load-' . $this->pagehook, array( $this, 'boxes_init' ) );
	}

	/**
	 * Prints default CSS styles.
	 *
	 * @return void
	 */
	protected function default_css() {
?>
<style type="text/css">
.postbox-container + .postbox-container {
	margin-left: 18px;
}
.postbox-container {
	padding-right: 0;
}
.inside {
	clear: both;
	overflow: hidden;
}
.inside table {
	margin: 0 !important;
	padding: 0 !important;
}
.inside table td {
	vertical-align: middle !important;
}
.inside table .regular-text {
	width: 100% !important;
}
.inside .form-table th {
	width: 30%;
	max-width: 200px;
	padding: 10px 0 !important;
}
.inside .widefat .check-column {
	padding-bottom: 7px !important;
}
.inside p,
.inside table {
	margin: 0 0 10px !important;
}
.inside p.submit {
	float: left !important;
	padding: 0 !important;
	margin-bottom: 0 !important;
}
.meta-box-sortables {
	min-height: 100px;
	width: 100%;
}
</style>
<?php
	}

	/**
	 * Displays page content.
	 *
	 * @return void
	 */
	protected function page_content() {
		$this->default_css();

		global $screen_layout_columns;

		if ( isset( $screen_layout_columns ) ) {
			$hide2 = $hide3 = $hide4 = '';
			switch ( $screen_layout_columns ) {
				case 4:
					if ( ! isset( $this->args['column_widths'] ) ) {
						$this->args['column_widths'] = array( 24.5, 24.5, 24.5, 24.5 );
					}
					break;
				case 3:
					if ( ! isset( $this->args['column_widths'] ) ) {
						$this->args['column_widths'] = array( 32.67, 32.67, 32.67 );
					}
					$hide4 = 'display:none;';
					break;
				case 2:
					if ( ! isset( $this->args['column_widths'] ) ) {
						$this->args['column_widths'] = array( 49, 49 );
					}
					$hide3 = $hide4 = 'display:none;';
					break;
				default:
					if ( ! isset( $this->args['column_widths'] ) ) {
						$this->args['column_widths'] = array( 98 );
					}
					$hide2 = $hide3 = $hide4 = 'display:none;';
			}

			$this->args['column_widths'] = array_pad( $this->args['column_widths'], 4, 0 );
		}
?>
<div id='<?php echo $this->pagehook; ?>-widgets' class='metabox-holder'>
<?php
	echo "\t<div class='postbox-container' style='width:{$this->args['column_widths'][0]}%'>\n";
	do_meta_boxes( $this->pagehook, 'normal', '' );

	echo "\t</div><div class='postbox-container' style='width:{$hide2}{$this->args['column_widths'][1]}%'>\n";
	do_meta_boxes( $this->pagehook, 'side', '' );

	echo "\t</div><div class='postbox-container' style='width:{$hide3}{$this->args['column_widths'][2]}%'>\n";
	do_meta_boxes( $this->pagehook, 'column3', '' );

	echo "\t</div><div class='postbox-container' style='width:{$hide4}{$this->args['column_widths'][3]}%'>\n";
	do_meta_boxes( $this->pagehook, 'column4', '' );
?>
</div></div>
<?php
	}

	/**
	 * Displays page footer.
	 *
	 * @return void
	 */
	protected function page_footer() {
		parent::page_footer();
		$this->_boxes_js_init();
	}

	/**
	 * Handles option saving.
	 *
	 * @return void
	 */
	protected function form_handler() {
		if ( empty( $_POST ) ) {
			return;
		}

		check_admin_referer( $this->nonce );

		// Box handler
		foreach ( $this->boxes as $box ) {
			$args = isset( $box[4] ) ? $box[4] : array();

			$handler = $box[0] . '_handler';

			if ( method_exists( $this, $handler ) ) {
				call_user_func_array( array( $this, $handler ), $args );
			}
		}
	}

	/**
	 * Uninstalls boxes.
	 *
	 * @return void
	 */
	public function uninstall() {
		global $wpdb;

		$hook = str_replace( '-', '', $this->pagehook );

		foreach ( array( 'metaboxhidden', 'closedpostboxes', 'wp_metaboxorder', 'screen_layout' ) as $option ) {
			$keys[] = "'{$option}_{$hook}'";
		}

		$keys = '( ' . implode( ', ', $keys ) . ' )';

		$wpdb->query( "
			DELETE FROM {$wpdb->usermeta}
			WHERE meta_key IN {$keys}
		" );
	}

	/**
	 * Adds boxes.
	 *
	 * @return void
	 */
	public function boxes_init() {
		wp_enqueue_script( 'postbox' );

		add_screen_option( 'layout_columns', array(
			'max' => $this->args['columns'],
			'default' => $this->args['columns']
		) );

		$registered = array();

		foreach ( $this->boxes as $box_args ) {
			$box_args = self::numeric_to_assoc( $box_args, array( 'name', 'title', 'context', 'priority', 'args' ) );

			$defaults = array(
				'title' => ucfirst( $box_args['name'] ),
				'context' => 'normal',
				'priority' => 'default',
				'args' => array()
			);
			$box_args = array_merge( $defaults, $box_args );

			$name = $box_args['name'];

			if ( isset( $registered[ $name ] ) ) {
				if ( empty( $box_args['args'] ) ) {
					trigger_error( "Duplicate box name: $name", E_USER_NOTICE );
				}

				$name = $this->_increment( $name );
			} else {
				$registered[ $name ] = true;
			}

			add_meta_box(
				$name,
				$box_args['title'],
				array( $this, '_intermediate_callback' ),
				$this->pagehook,
				$box_args['context'],
				$box_args['priority'],
				$box_args['args']
			);
		}
	}

	/**
	 * Transforms numeric array to associative.
	 *
	 * @param array $argv
	 * @param array $keys
	 *
	 * @return array
	 */
	private static function numeric_to_assoc( $argv, $keys ) {
		$args = array();

		foreach ( $keys as $i => $key ) {
			if ( isset( $argv[ $i ] ) ) {
				$args[ $key ] = $argv[ $i ];
			}
		}

		return $args;
	}

	/**
	 * Since we don't pass an object to do_meta_boxes(),
	 * pass $box['args'] directly to each method.
	 *
	 * @param string $_
	 * @param array $box
	 *
	 * @return void
	 */
	public function _intermediate_callback( $_, $box ) {
		list( $name ) = explode( '-', $box['id'] );

		call_user_func_array( array( $this, $name . '_box' ), $box['args'] );
	}

	/**
	 * Adds/Increments ID in box name.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	private function _increment( $name ) {
		$parts = explode( '-', $name );
		if ( isset( $parts[1] ) ) {
			$parts[1]++;
		} else {
			$parts[1] = 2;
		}

		return implode( '-', $parts );
	}

	/**
	 * Adds necesary code for JS to work.
	 *
	 * @return void
	 */
	protected function _boxes_js_init() {
		echo $this->js_wrap( <<<EOT
jQuery( document ).ready( function( $ ){
	// close postboxes that should be closed
	$( '.if-js-closed' ).removeClass( 'if-js-closed' ).addClass( 'closed' );
	// postboxes setup
	postboxes.add_postbox_toggles( '$this->pagehook' );
} );
EOT
		);
?>

<form style='display: none' method='get' action=''>
	<p>
<?php
	wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
	wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
?>
	</p>
</form>
<?php
	}
}

