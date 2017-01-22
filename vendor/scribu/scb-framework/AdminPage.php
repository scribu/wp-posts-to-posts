<?php
/**
 * Administration page base class.
 */
abstract class scbAdminPage {
	/** Page args
	 * $page_title string (mandatory)
	 * $parent (string)  (default: options-general.php)
	 * $capability (string)  (default: 'manage_options')
	 * $menu_title (string)  (default: $page_title)
	 * $submenu_title (string)  (default: $menu_title)
	 * $page_slug (string)  (default: sanitized $page_title)
	 * $toplevel (string)  If not empty, will create a new top level menu (for expected values see http://codex.wordpress.org/Administration_Menus#Using_add_submenu_page)
	 * - $icon_url (string)  URL to an icon for the top level menu
	 * - $position (int)  Position of the toplevel menu (caution!)
	 * $screen_icon (string)  The icon type to use in the screen header
	 * $nonce string  (default: $page_slug)
	 * $action_link (string|bool)  Text of the action link on the Plugins page (default: 'Settings')
	 * $admin_action_priority int  The priority that the admin_menu action should be executed at (default: 10)
	 */
	protected $args;

	// URL to the current plugin directory.
	// Useful for adding css and js files
	protected $plugin_url;

	// Created at page init
	protected $pagehook;

	// scbOptions object holder
	// Normally, it's used for storing formdata
	protected $options;
	protected $option_name;

	// l10n
	protected $textdomain;


//  ____________REGISTRATION COMPONENT____________


	private static $registered = array();

	/**
	 * Registers class of page.
	 *
	 * @param string $class
	 * @param string $file
	 * @param object $options (optional) A scbOptions object.
	 *
	 * @return bool
	 */
	public static function register( $class, $file, $options = null ) {
		if ( isset( self::$registered[ $class ] ) ) {
			return false;
		}

		self::$registered[ $class ] = array( $file, $options );

		add_action( '_admin_menu', array( __CLASS__, '_pages_init' ) );

		return true;
	}

	/**
	 * Replaces class of page.
	 *
	 * @param string $old_class
	 * @param string $new_class
	 *
	 * @return bool
	 */
	public static function replace( $old_class, $new_class ) {
		if ( ! isset( self::$registered[ $old_class ] ) ) {
			return false;
		}

		self::$registered[ $new_class ] = self::$registered[ $old_class ];
		unset( self::$registered[ $old_class ] );

		return true;
	}

	/**
	 * Removes class of page.
	 *
	 * @param string $class
	 *
	 * @return bool
	 */
	public static function remove( $class ) {
		if ( ! isset( self::$registered[ $class ] ) ) {
			return false;
		}

		unset( self::$registered[ $class ] );

		return true;
	}

	/**
	 * Instantiates classes of pages.
	 *
	 * @return void
	 */
	public static function _pages_init() {
		foreach ( self::$registered as $class => $args ) {
			new $class( $args[0], $args[1] );
		}
	}


//  ____________MAIN METHODS____________


	/**
	 * Constructor.
	 *
	 * @param string|bool $file (optional)
	 * @param object $options (optional) A scbOptions object.
	 *
	 * @return void
	 */
	public function __construct( $file = false, $options = null ) {
		if ( is_a( $options, 'scbOptions' ) ) {
			$this->options = $options;
		}

		$this->setup();
		$this->check_args();

		if ( isset( $this->option_name ) ) {
			add_action( 'admin_init', array( $this, 'option_init' ) );
			add_action( 'admin_notices', 'settings_errors' );
		}

		add_action( 'admin_menu', array( $this, 'page_init' ), $this->args['admin_action_priority'] );
		add_filter( 'contextual_help', array( $this, '_contextual_help' ), 10, 2 );

		if ( $file ) {
			$this->file = $file;
			$this->plugin_url = plugin_dir_url( $file );

			if ( $this->args['action_link'] ) {
				add_filter( 'plugin_action_links_' . plugin_basename( $file ), array( $this, '_action_link' ) );
			}
		}
	}

	/**
	 * This is where all the page args can be set.
	 *
	 * @return void
	 */
	protected function setup() { }

	/**
	 * Called when the page is loaded, but before any rendering.
	 * Useful for calling $screen->add_help_tab() etc.
	 *
	 * @return void
	 */
	public function page_loaded() {
		$this->form_handler();
	}

	/**
	 * This is where the css and js go.
	 * Both wp_enqueue_*() and inline code can be added.
	 *
	 * @return void
	 */
	public function page_head() { }

	/**
	 * This is where the contextual help goes.
	 *
	 * @return string
	 */
	protected function page_help() { }

	/**
	 * A generic page header.
	 *
	 * @return void
	 */
	protected function page_header() {
		echo "<div class='wrap'>\n";
		screen_icon( $this->args['screen_icon'] );
		echo html( 'h2', $this->args['page_title'] );
	}

	/**
	 * This is where the page content goes.
	 *
	 * @return void
	 */
	abstract protected function page_content();

	/**
	 * A generic page footer.
	 *
	 * @return void
	 */
	protected function page_footer() {
		echo "</div>\n";
	}

	/**
	 * This is where the form data should be validated.
	 *
	 * @param array $new_data
	 * @param array $old_data
	 *
	 * @return array
	 */
	public function validate( $new_data, $old_data ) {
		return $new_data;
	}

	/**
	 * Manually handle option saving ( use Settings API instead ).
	 *
	 * @return bool
	 */
	protected function form_handler() {
		if ( empty( $_POST['submit'] ) && empty( $_POST['action'] ) ) {
			return false;
		}

		check_admin_referer( $this->nonce );

		if ( ! isset( $this->options ) ) {
			trigger_error( 'options handler not set', E_USER_WARNING );
			return false;
		}

		$new_data = wp_array_slice_assoc( $_POST, array_keys( $this->options->get_defaults() ) );

		$new_data = stripslashes_deep( $new_data );

		$new_data = $this->validate( $new_data, $this->options->get() );

		$this->options->set( $new_data );

		add_action( 'admin_notices', array( $this, 'admin_msg' ) );

		return true;
	}

	/**
	 * Manually generate a standard admin notice ( use Settings API instead ).
	 *
	 * @param string $msg (optional)
	 * @param string $class (optional)
	 *
	 * @return void
	 */
	public function admin_msg( $msg = '', $class = 'updated' ) {
		if ( empty( $msg ) ) {
			$msg = __( 'Settings <strong>saved</strong>.', $this->textdomain );
		}

		echo scb_admin_notice( $msg, $class );
	}


//  ____________UTILITIES____________


	/**
	 * Generates a form submit button.
	 *
	 * @param string|array $value (optional) Button text or array of arguments.
	 * @param string       $action (optional)
	 * @param string       $class (optional)
	 *
	 * @return string
	 */
	public function submit_button( $value = '', $action = 'submit', $class = 'button' ) {

		$args = is_array( $value ) ? $value : compact( 'value', 'action', 'class' );
		$args = wp_parse_args( $args, array(
			'value'  => null,
			'action' => $action,
			'class'  => $class,
		) );

		return get_submit_button( $args['value'], $args['class'], $args['action'] );
	}

	/**
	 * Mimics scbForms::form_wrap()
	 *
	 * $this->form_wrap( $content );  // generates a form with a default submit button
	 *
	 * $this->form_wrap( $content, false ); // generates a form with no submit button
	 *
	 *  // the second argument is sent to submit_button()
	 *  $this->form_wrap( $content, array(
	 *      'text' => 'Save changes',
	 *      'name' => 'action',
	 *  ) );
	 *
	 * @see scbForms::form_wrap()
	 *
	 * @param string               $content
	 * @param boolean|string|array $submit_button (optional)
	 *
	 * @return string
	 */
	public function form_wrap( $content, $submit_button = true ) {
		if ( is_array( $submit_button ) ) {
			$content .= $this->submit_button( $submit_button );
		} else if ( true === $submit_button ) {
			$content .= $this->submit_button();
		} else if ( false !== strpos( $submit_button, '<input' ) ) {
			$content .= $submit_button;
		} else if ( false !== strpos( $submit_button, '<button' ) ) {
			$content .= $submit_button;
		} else if ( false !== $submit_button ) {
			$button_args = array_slice( func_get_args(), 1 );
			$content    .= call_user_func_array( array( $this, 'submit_button' ), $button_args );
		}

		return scbForms::form_wrap( $content, $this->nonce );
	}

	/**
	 * Generates a table wrapped in a form.
	 *
	 * @param array         $rows
	 * @param array|boolean $formdata (optional)
	 *
	 * @return string
	 */
	public function form_table( $rows, $formdata = false ) {
		$output = '';
		foreach ( $rows as $row ) {
			$output .= $this->table_row( $row, $formdata );
		}

		$output = $this->form_table_wrap( $output );

		return $output;
	}

	/**
	 * Wraps the given content in a <form><table>
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function form_table_wrap( $content ) {
		$output = $this->table_wrap( $content );
		$output = $this->form_wrap( $output );

		return $output;
	}

	/**
	 * Generates a form table.
	 *
	 * @param array         $rows
	 * @param array|boolean $formdata (optional)
	 *
	 * @return string
	 */
	public function table( $rows, $formdata = false ) {
		$output = '';
		foreach ( $rows as $row ) {
			$output .= $this->table_row( $row, $formdata );
		}

		$output = $this->table_wrap( $output );

		return $output;
	}

	/**
	 * Generates a table row.
	 *
	 * @param array         $args
	 * @param array|boolean $formdata (optional)
	 *
	 * @return string
	 */
	public function table_row( $args, $formdata = false ) {
		return $this->row_wrap( $args['title'], $this->input( $args, $formdata ) );
	}

	/**
	 * Mimic scbForms inheritance.
	 *
	 * @see scbForms
	 *
	 * @param string $method
	 * @param array  $args
	 *
	 * @return mixed
	 */
	public function __call( $method, $args ) {
		if ( in_array( $method, array( 'input', 'form' ) ) ) {
			if ( empty( $args[1] ) && isset( $this->options ) ) {
				$args[1] = $this->options->get();
			}

			if ( 'form' == $method ) {
				$args[2] = $this->nonce;
			}
		}

		return call_user_func_array( array( 'scbForms', $method ), $args );
	}

	/**
	 * Wraps a string in a <script> tag.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public function js_wrap( $string ) {
		return html( "script type='text/javascript'", $string );
	}

	/**
	 * Wraps a string in a <style> tag.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public function css_wrap( $string ) {
		return html( "style type='text/css'", $string );
	}


//  ____________INTERNAL METHODS____________


	/**
	 * Registers a page.
	 *
	 * @return void
	 */
	public function page_init() {

		if ( ! $this->args['toplevel'] ) {
			$this->pagehook = add_submenu_page(
				$this->args['parent'],
				$this->args['page_title'],
				$this->args['menu_title'],
				$this->args['capability'],
				$this->args['page_slug'],
				array( $this, '_page_content_hook' )
			);
		} else {
			$func = 'add_' . $this->args['toplevel'] . '_page';
			$this->pagehook = $func(
				$this->args['page_title'],
				$this->args['menu_title'],
				$this->args['capability'],
				$this->args['page_slug'],
				null,
				$this->args['icon_url'],
				$this->args['position']
			);

			add_submenu_page(
				$this->args['page_slug'],
				$this->args['page_title'],
				$this->args['submenu_title'],
				$this->args['capability'],
				$this->args['page_slug'],
				array( $this, '_page_content_hook' )
			);
		}

		if ( ! $this->pagehook ) {
			return;
		}

		add_action( 'load-' . $this->pagehook, array( $this, 'page_loaded' ) );

		add_action( 'admin_print_styles-' . $this->pagehook, array( $this, 'page_head' ) );
	}

	/**
	 * Registers a option.
	 *
	 * @return void
	 */
	public function option_init() {
		register_setting( $this->option_name, $this->option_name, array( $this, 'validate' ) );
	}

	/**
	 * Checks page args.
	 *
	 * @return void
	 */
	private function check_args() {
		if ( empty( $this->args['page_title'] ) ) {
			trigger_error( 'Page title cannot be empty', E_USER_WARNING );
		}

		$this->args = wp_parse_args( $this->args, array(
			'toplevel'              => '',
			'position'              => null,
			'icon_url'              => '',
			'screen_icon'           => '',
			'parent'                => 'options-general.php',
			'capability'            => 'manage_options',
			'menu_title'            => $this->args['page_title'],
			'page_slug'             => '',
			'nonce'                 => '',
			'action_link'           => __( 'Settings', $this->textdomain ),
			'admin_action_priority' => 10,
		) );

		if ( empty( $this->args['submenu_title'] ) ) {
			$this->args['submenu_title'] = $this->args['menu_title'];
		}

		if ( empty( $this->args['page_slug'] ) ) {
			$this->args['page_slug'] = sanitize_title_with_dashes( $this->args['menu_title'] );
		}

		if ( empty( $this->args['nonce'] ) ) {
			$this->nonce = $this->args['page_slug'];
		}
	}

	/**
	 * Adds contextual help.
	 *
	 * @param string        $help
	 * @param string|object $screen
	 *
	 * @return string
	 */
	public function _contextual_help( $help, $screen ) {
		if ( is_object( $screen ) ) {
			$screen = $screen->id;
		}

		$actual_help = $this->page_help();

		if ( $screen == $this->pagehook && $actual_help ) {
			return $actual_help;
		}

		return $help;
	}

	/**
	 * Displays page content.
	 *
	 * @return void
	 */
	public function _page_content_hook() {
		$this->page_header();
		$this->page_content();
		$this->page_footer();
	}

	/**
	 * Adds an action link.
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function _action_link( $links ) {
		$url = add_query_arg( 'page', $this->args['page_slug'], admin_url( $this->args['parent'] ) );

		$links[] = html_link( $url, $this->args['action_link'] );

		return $links;
	}
}

