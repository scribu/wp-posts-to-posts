<?php

/*
Creates an admin page with widgets, similar to the dashboard

For example, if you defined the boxes like this:

$this->boxes = array(
	array('settings', 'Settings box', 'normal')
	...
);

You must also define two methods in your class for each box:

function settings_box() - this is where the box content is echoed
function settings_handler() - this is where the box settings are saved
...
*/
abstract class scbBoxesPage extends scbAdminPage {
	/*
		A box definition looks like this:
		array($slug, $title, $column);

		Available columns: normal, side, column3, column4
	*/
	protected $boxes = array();

	function __construct($file, $options = null) {
		parent::__construct($file, $options);

		// too late
		scbUtil::add_uninstall_hook($this->file, array($this, 'uninstall'));
	}

	function page_init() {
		if ( !isset($this->args['columns']) )
			$this->args['columns'] = 2;

		parent::page_init();

		add_action('load-' . $this->pagehook, array($this, 'boxes_init'));
		add_filter('screen_layout_columns', array($this, 'columns'));
	}

	function default_css() {
?>
<style type="text/css">
.postbox-container + .postbox-container {margin-left: 18px}
.postbox-container {padding-right: 0}

.inside {clear: both; overflow: hidden; padding: 10px 10px 0 10px !important}
.inside table {margin: 0 !important; padding: 0 !important}
.inside table td {vertical-align: middle !important}
.inside table .regular-text {width: 100% !important}
.inside .form-table th {width: 30%; max-width: 200px; padding: 10px 0 !important}
.inside .widefat .check-column {padding-bottom: 7px !important}
.inside p, .inside table {margin: 0 0 10px 0 !important}
.inside p.submit {float:left !important; padding: 0 !important}
</style>
<?php
	}

	function page_content() {
		$this->default_css();

		global $screen_layout_columns;

		if ( isset($screen_layout_columns) ) {
			$hide2 = $hide3 = $hide4 = '';
			switch ( $screen_layout_columns ) {
				case 4:
					$width = 'width:24.5%;';
					break;
				case 3:
					$width = 'width:32.67%;';
					$hide4 = 'display:none;';
					break;
				case 2:
					$width = 'width:49%;';
					$hide3 = $hide4 = 'display:none;';
					break;
				default:
					$width = 'width:98%;';
					$hide2 = $hide3 = $hide4 = 'display:none;';
			}
		}
?>
<div id='<?php echo $this->pagehook ?>-widgets' class='metabox-holder'>
<?php
	echo "\t<div class='postbox-container' style='$width'>\n";
	do_meta_boxes( $this->pagehook, 'normal', '' );

	echo "\t</div><div class='postbox-container' style='{$hide2}$width'>\n";
	do_meta_boxes( $this->pagehook, 'side', '' );

	echo "\t</div><div class='postbox-container' style='{$hide3}$width'>\n";
	do_meta_boxes( $this->pagehook, 'column3', '' );

	echo "\t</div><div class='postbox-container' style='{$hide4}$width'>\n";
	do_meta_boxes( $this->pagehook, 'column4', '' );
?>
</div></div>
<?php
	}

	function page_footer() {
		parent::page_footer();
		$this->_boxes_js_init();
	}

	function form_handler() {
		if ( empty($_POST) )
			return;

		check_admin_referer($this->nonce);

		// Box handler
		foreach ( $this->boxes as $box ) {
			$args = isset($box[4]) ? $box[4] : array();

			$handler = $box[0] . '_handler';

			if ( method_exists($this, $handler) )
				call_user_func_array(array($this, $handler), $args);
		}

		if ( $this->options )
			$this->formdata = $this->options->get();
	}

	function columns($columns) {
		$columns[$this->pagehook] = $this->args['columns'];

		return $columns;
	}

	function uninstall() {
		global $wpdb;

		$hook = str_replace('-', '', $this->pagehook);

		foreach ( array('metaboxhidden', 'closedpostboxes', 'wp_metaboxorder', 'screen_layout') as $option )
			$keys[] = "'{$option}_{$hook}'";

		$keys = '(' . implode(', ', $keys) . ')';

		$wpdb->query("
			DELETE FROM {$wpdb->usermeta}
			WHERE meta_key IN {$keys}
		");
	}

	function boxes_init() {
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');

		$registered = array();
		foreach($this->boxes as $box_args) {
			@list($name, $title, $context, $priority, $args) = $box_args;

			if ( empty($title) )
				$title = ucfirst($name);
			if ( empty($context) )
				$context = 'normal';
			if ( empty($priority) )
				$priority = 'default';
			if ( empty($args) )
				$args = array();

			if ( isset($registered[$name]) ) {
				if ( empty($args) )
					trigger_error("Duplicate box name: $name", E_USER_NOTICE);

				$name = $this->_increment($name);
			} else {
				$registered[$name] = true;
			}

			add_meta_box($name, $title, array($this, '_intermediate_callback'), $this->pagehook, $context, $priority, $args);
		}
	}

	// Make it so that $args is actually what's passed to the callback
	function _intermediate_callback($_, $box) {
		list($name) = explode('-', $box['id']);

		call_user_func_array(array($this, $name . '_box'), $box['args']);
	}

	private function _increment($name) {
		$parts = explode('-', $name);
		if ( isset($parts[1]) )
			$parts[1]++;
		else
			$parts[1] = 2;

		return implode('-', $parts);
	}

	// Adds necesary code for JS to work
	function _boxes_js_init() {
		echo $this->js_wrap(
<<<EOT
jQuery(document).ready(function($){
	// close postboxes that should be closed
	$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
	// postboxes setup
	postboxes.add_postbox_toggles('$this->pagehook');
});
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

