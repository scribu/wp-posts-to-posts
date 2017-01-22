<?php
/**
 * Data-aware form generator.
 */
class scbForms {

	const TOKEN = '%input%';

	/**
	 * Generates form field.
	 *
	 * @param array|scbFormField_I $args
	 * @param mixed                $value
	 *
	 * @return string
	 */
	public static function input_with_value( $args, $value ) {
		$field = scbFormField::create( $args );

		return $field->render( $value );
	}

	/**
	 * Generates form field.
	 *
	 * @param array|scbFormField_I $args
	 * @param array                $formdata (optional)
	 *
	 * @return string
	 */
	public static function input( $args, $formdata = null ) {
		$field = scbFormField::create( $args );

		return $field->render( scbForms::get_value( $args['name'], $formdata ) );
	}

	/**
	 * Generates a table wrapped in a form.
	 *
	 * @param array $rows
	 * @param array $formdata (optional)
	 *
	 * @return string
	 */
	public static function form_table( $rows, $formdata = null ) {
		$output = '';
		foreach ( $rows as $row ) {
			$output .= self::table_row( $row, $formdata );
		}

		$output = self::form_table_wrap( $output );

		return $output;
	}

	/**
	 * Generates a form.
	 *
	 * @param array  $inputs
	 * @param array  $formdata (optional)
	 * @param string $nonce
	 *
	 * @return string
	 */
	public static function form( $inputs, $formdata = null, $nonce ) {
		$output = '';
		foreach ( $inputs as $input ) {
			$output .= self::input( $input, $formdata );
		}

		$output = self::form_wrap( $output, $nonce );

		return $output;
	}

	/**
	 * Generates a table.
	 *
	 * @param array $rows
	 * @param array $formdata (optional)
	 *
	 * @return string
	 */
	public static function table( $rows, $formdata = null ) {
		$output = '';
		foreach ( $rows as $row ) {
			$output .= self::table_row( $row, $formdata );
		}

		$output = self::table_wrap( $output );

		return $output;
	}

	/**
	 * Generates a table row.
	 *
	 * @param array $args
	 * @param array $formdata (optional)
	 *
	 * @return string
	 */
	public static function table_row( $args, $formdata = null ) {
		return self::row_wrap( $args['title'], self::input( $args, $formdata ) );
	}


// ____________WRAPPERS____________

	/**
	 * Wraps a table in a form.
	 *
	 * @param string $content
	 * @param string $nonce (optional)
	 *
	 * @return string
	 */
	public static function form_table_wrap( $content, $nonce = 'update_options' ) {
		return self::form_wrap( self::table_wrap( $content ), $nonce );
	}

	/**
	 * Wraps a content in a form.
	 *
	 * @param string $content
	 * @param string $nonce (optional)
	 *
	 * @return string
	 */
	public static function form_wrap( $content, $nonce = 'update_options' ) {
		return html( "form method='post' action=''",
			$content,
			wp_nonce_field( $nonce, '_wpnonce', $referer = true, $echo = false )
		);
	}

	/**
	 * Wraps a content in a table.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public static function table_wrap( $content ) {
		return html( "table class='form-table'", $content );
	}

	/**
	 * Wraps a content in a table row.
	 *
	 * @param string $title
	 * @param string $content
	 *
	 * @return string
	 */
	public static function row_wrap( $title, $content ) {
		return html( 'tr',
			html( "th scope='row'", $title ),
			html( 'td', $content )
		);
	}


// ____________PRIVATE METHODS____________


// Utilities


	/**
	 * Generates the proper string for a name attribute.
	 *
	 * @param array|string $name The raw name
	 *
	 * @return string
	 */
	public static function get_name( $name ) {
		$name = (array) $name;

		$name_str = array_shift( $name );

		foreach ( $name as $key ) {
			$name_str .= '[' . esc_attr( $key ) . ']';
		}

		return $name_str;
	}

	/**
	 * Traverses the formdata and retrieves the correct value.
	 *
	 * @param string $name     The name of the value
	 * @param array  $value    The data that will be traversed
	 * @param mixed  $fallback (optional) The value returned when the key is not found
	 *
	 * @return mixed
	 */
	public static function get_value( $name, $value, $fallback = null ) {
		foreach ( (array) $name as $key ) {
			if ( ! isset( $value[ $key ] ) ) {
				return $fallback;
			}

			$value = $value[ $key ];
		}

		return $value;
	}

	/**
	 * Given a list of fields, validate some data.
	 *
	 * @param array $fields    List of args that would be sent to scbForms::input()
	 * @param array $data      (optional) The data to validate. Defaults to $_POST
	 * @param array $to_update (optional) Existing data to populate. Necessary for nested values
	 *
	 * @return array
	 */
	public static function validate_post_data( $fields, $data = null, $to_update = array() ) {
		if ( null === $data ) {
			$data = stripslashes_deep( $_POST );
		}

		foreach ( $fields as $field ) {
			$value = scbForms::get_value( $field['name'], $data );

			$fieldObj = scbFormField::create( $field );

			$value = $fieldObj->validate( $value );

			if ( null !== $value ) {
				self::set_value( $to_update, $field['name'], $value );
			}
		}

		return $to_update;
	}

	/**
	 * For multiple-choice fields, we can never distinguish between "never been set" and "set to none".
	 * For single-choice fields, we can't distinguish either, because of how self::update_meta() works.
	 * Therefore, the 'default' parameter is always ignored.
	 *
	 * @param array  $args      Field arguments.
	 * @param int    $object_id The object ID the metadata is attached to
	 * @param string $meta_type (optional)
	 *
	 * @return string
	 */
	public static function input_from_meta( $args, $object_id, $meta_type = 'post' ) {
		$single = ( 'checkbox' != $args['type'] );

		$key = (array) $args['name'];
		$key = end( $key );

		$value = get_metadata( $meta_type, $object_id, $key, $single );

		return self::input_with_value( $args, $value );
	}

	/**
	 * Updates metadata for passed list of fields.
	 *
	 * @param array  $fields
	 * @param array  $data
	 * @param int    $object_id The object ID the metadata is attached to
	 * @param string $meta_type (optional) Defaults to 'post'
	 *
	 * @return void
	 */
	public static function update_meta( $fields, $data, $object_id, $meta_type = 'post' ) {
		foreach ( $fields as $field_args ) {
			$key = $field_args['name'];

			if ( 'checkbox' == $field_args['type'] ) {
				$new_values = isset( $data[ $key ] ) ? $data[ $key ] : array();

				$old_values = get_metadata( $meta_type, $object_id, $key );

				foreach ( array_diff( $new_values, $old_values ) as $value ) {
					add_metadata( $meta_type, $object_id, $key, $value );
				}

				foreach ( array_diff( $old_values, $new_values ) as $value ) {
					delete_metadata( $meta_type, $object_id, $key, $value );
				}
			} else {
				$value = isset( $data[ $key ] ) ? $data[ $key ] : '';

				if ( '' === $value ) {
					delete_metadata( $meta_type, $object_id, $key );
				} else {
					update_metadata( $meta_type, $object_id, $key, $value );
				}
			}
		}
	}

	/**
	 * Sets value using a reference.
	 *
	 * @param array  $arr
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return void
	 */
	private static function set_value( &$arr, $name, $value ) {
		$name = (array) $name;

		$final_key = array_pop( $name );

		while ( ! empty( $name ) ) {
			$key = array_shift( $name );

			if ( ! isset( $arr[ $key ] ) ) {
				$arr[ $key ] = array();
			}

			$arr =& $arr[ $key ];
		}

		$arr[ $final_key ] = $value;
	}
}


/**
 * A wrapper for scbForms, containing the formdata.
 */
class scbForm {
	protected $data   = array();
	protected $prefix = array();

	/**
	 * Constructor.
	 *
	 * @param array          $data
	 * @param string|boolean $prefix (optional)
	 *
	 * @return void
	 */
	public function __construct( $data, $prefix = false ) {
		if ( is_array( $data ) ) {
			$this->data = $data;
		}

		if ( $prefix ) {
			$this->prefix = (array) $prefix;
		}
	}

	/**
	 * Traverses the form.
	 *
	 * @param string $path
	 *
	 * @return object A scbForm
	 */
	public function traverse_to( $path ) {
		$data = scbForms::get_value( $path, $this->data );

		$prefix = array_merge( $this->prefix, (array) $path );

		return new scbForm( $data, $prefix );
	}

	/**
	 * Generates form field.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	public function input( $args ) {
		$value = scbForms::get_value( $args['name'], $this->data );

		if ( ! empty( $this->prefix ) ) {
			$args['name'] = array_merge( $this->prefix, (array) $args['name'] );
		}

		return scbForms::input_with_value( $args, $value );
	}
}

/**
 * Interface for form fields.
 */
interface scbFormField_I {

	/**
	 * Generate the corresponding HTML for a field.
	 *
	 * @param mixed $value (optional) The value to use.
	 *
	 * @return string
	 */
	function render( $value = null );

	/**
	 * Validates a value against a field.
	 *
	 * @param mixed $value The value to check.
	 *
	 * @return mixed null if the validation failed, sanitized value otherwise.
	 */
	function validate( $value );
}

/**
 * Base class for form fields implementations.
 */
abstract class scbFormField implements scbFormField_I {

	protected $args;

	/**
	 * Creates form field.
	 *
	 * @param array|scbFormField_I $args
	 *
	 * @return mixed false on failure or instance of form class
	 */
	public static function create( $args ) {
		if ( is_a( $args, 'scbFormField_I' ) ) {
			return $args;
		}

		if ( empty( $args['name'] ) ) {
			return trigger_error( 'Empty name', E_USER_WARNING );
		}

		if ( isset( $args['value'] ) && is_array( $args['value'] ) ) {
			$args['choices'] = $args['value'];
			unset( $args['value'] );
		}

		if ( isset( $args['values'] ) ) {
			$args['choices'] = $args['values'];
			unset( $args['values'] );
		}

		if ( isset( $args['extra'] ) && ! is_array( $args['extra'] ) ) {
			$args['extra'] = shortcode_parse_atts( $args['extra'] );
		}

		$args = wp_parse_args( $args, array(
			'desc'      => '',
			'desc_pos'  => 'after',
			'wrap'      => scbForms::TOKEN,
			'wrap_each' => scbForms::TOKEN,
		) );

		// depends on $args['desc']
		if ( isset( $args['choices'] ) ) {
			self::_expand_choices( $args );
		}

		switch ( $args['type'] ) {
			case 'radio':
				return new scbRadiosField( $args );
			case 'select':
				return new scbSelectField( $args );
			case 'checkbox':
				if ( isset( $args['choices'] ) ) {
					return new scbMultipleChoiceField( $args );
				} else {
					return new scbSingleCheckboxField( $args );
				}
			case 'custom':
				return new scbCustomField( $args );
			default:
				return new scbTextField( $args );
		}
	}

	/**
	 * Constructor.
	 *
	 * @param array $args
	 *
	 * @return void
	 */
	protected function __construct( $args ) {
		$this->args = $args;
	}

	/**
	 * Magic method: $field->arg
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		return $this->args[ $key ];
	}

	/**
	 * Magic method: isset( $field->arg )
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function __isset( $key ) {
		return isset( $this->args[ $key ] );
	}

	/**
	 * Generate the corresponding HTML for a field.
	 *
	 * @param mixed $value (optional)
	 *
	 * @return string
	 */
	public function render( $value = null ) {
		if ( null === $value && isset( $this->default ) ) {
			$value = $this->default;
		}

		$args = $this->args;

		if ( null !== $value ) {
			$this->_set_value( $args, $value );
		}

		$args['name'] = scbForms::get_name( $args['name'] );

		return str_replace( scbForms::TOKEN, $this->_render( $args ), $this->wrap );
	}

	/**
	 * Mutate the field arguments so that the value passed is rendered.
	 *
	 * @param array  $args
	 * @param mixed  $value
	 */
	abstract protected function _set_value( &$args, $value );

	/**
	 * The actual rendering.
	 *
	 * @param array $args
	 */
	abstract protected function _render( $args );

	/**
	 * Handle args for a single checkbox or radio input.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	protected static function _checkbox( $args ) {
		$args = wp_parse_args( $args, array(
			'value'   => true,
			'desc'    => null,
			'checked' => false,
			'extra'   => array(),
		) );

		$args['extra']['checked'] = $args['checked'];

		if ( is_null( $args['desc'] ) && ! is_bool( $args['value'] ) ) {
			$args['desc'] = str_replace( '[]', '', $args['value'] );
		}

		return self::_input_gen( $args );
	}

	/**
	 * Generate html with the final args.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	protected static function _input_gen( $args ) {
		$args = wp_parse_args( $args, array(
			'value' => null,
			'desc'  => null,
			'extra' => array(),
		) );

		$args['extra']['name'] = $args['name'];

		if ( 'textarea' == $args['type'] ) {
			$input = html( 'textarea', $args['extra'], esc_textarea( $args['value'] ) );
		} else {
			$args['extra']['value'] = $args['value'];
			$args['extra']['type']  = $args['type'];
			$input = html( 'input', $args['extra'] );
		}

		return self::add_label( $input, $args['desc'], $args['desc_pos'] );
	}

	/**
	 * Wraps a form field in a label, and position field description.
	 *
	 * @param string $input
	 * @param string $desc
	 * @param string $desc_pos
	 *
	 * @return string
	 */
	protected static function add_label( $input, $desc, $desc_pos ) {
		return html( 'label', self::add_desc( $input, $desc, $desc_pos ) ) . "\n";
	}

	/**
	 * Adds description before/after the form field.
	 *
	 * @param string $input
	 * @param string $desc
	 * @param string $desc_pos
	 *
	 * @return string
	 */
	protected static function add_desc( $input, $desc, $desc_pos ) {
		if ( empty( $desc ) ) {
			return $input;
		}

		if ( 'before' == $desc_pos ) {
			return $desc . ' ' . $input;
		} else {
			return $input . ' ' . $desc;
		}
	}

	/**
	 * @param array $args
	 */
	private static function _expand_choices( &$args ) {
		$choices =& $args['choices'];

		if ( ! empty( $choices ) && ! self::is_associative( $choices ) ) {
			if ( is_array( $args['desc'] ) ) {
				$choices = array_combine( $choices, $args['desc'] );	// back-compat
				$args['desc'] = false;
			} else if ( ! isset( $args['numeric'] ) || ! $args['numeric'] ) {
				$choices = array_combine( $choices, $choices );
			}
		}
	}

	/**
	 * Checks if passed array is associative.
	 *
	 * @param array $array
	 *
	 * @return bool
	 */
	private static function is_associative( $array ) {
		$keys = array_keys( $array );
		return array_keys( $keys ) !== $keys;
	}
}

/**
 * Text form field.
 */
class scbTextField extends scbFormField {

	/**
	 * Sanitizes value.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	public function validate( $value ) {
		$sanitize = isset( $this->sanitize ) ? $this->sanitize : 'wp_kses_data';

		return call_user_func( $sanitize, $value, $this );
	}

	/**
	 * Generate the corresponding HTML for a field.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	protected function _render( $args ) {
		$args = wp_parse_args( $args, array(
			'value'    => '',
			'desc_pos' => 'after',
			'extra'    => array( 'class' => 'regular-text' ),
		) );

		if ( ! isset( $args['extra']['id'] ) && ! is_array( $args['name'] ) && false === strpos( $args['name'], '[' ) ) {
			$args['extra']['id'] = $args['name'];
		}

		return scbFormField::_input_gen( $args );
	}

	/**
	 * Sets value using a reference.
	 *
	 * @param array  $args
	 * @param string $value
	 *
	 * @return void
	 */
	protected function _set_value( &$args, $value ) {
		$args['value'] = $value;
	}
}

/**
 * Base class for form fields with single choice.
 */
abstract class scbSingleChoiceField extends scbFormField {

	/**
	 * Validates a value against a field.
	 *
	 * @param mixed $value
	 *
	 * @return mixed|null
	 */
	public function validate( $value ) {
		if ( isset( $this->choices[ $value ] ) ) {
			return $value;
		}

		return null;
	}

	/**
	 * Generate the corresponding HTML for a field.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	protected function _render( $args ) {
		$args = wp_parse_args( $args, array(
			'numeric'  => false, // use numeric array instead of associative
		) );

		if ( isset( $args['selected'] ) ) {
			$args['selected'] = (string) $args['selected'];
		} else {
			$args['selected'] = array( 'foo' );  // hack to make default blank
		}

		return $this->_render_specific( $args );
	}

	/**
	 * Sets value using a reference.
	 *
	 * @param array  $args
	 * @param string $value
	 *
	 * @return void
	 */
	protected function _set_value( &$args, $value ) {
		$args['selected'] = $value;
	}

	/**
	 * Generate the corresponding HTML for a field.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	abstract protected function _render_specific( $args );
}

/**
 * Dropdown field.
 */
class scbSelectField extends scbSingleChoiceField {

	/**
	 * Generate the corresponding HTML for a field.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	protected function _render_specific( $args ) {
		$args = wp_parse_args( $args, array(
			'text'  => false,
			'extra' => array(),
		) );

		$options = array();

		if ( false !== $args['text'] ) {
			$options[] = array(
				'value'    => '',
				'selected' => ( $args['selected'] === array( 'foo' ) ),
				'title'    => $args['text'],
			);
		}

		foreach ( $args['choices'] as $value => $title ) {
			$value = (string) $value;

			$options[] = array(
				'value'    => $value,
				'selected' => ( $value == $args['selected'] ),
				'title'    => $title,
			);
		}

		$opts = '';
		foreach ( $options as $option ) {
			$opts .= html( 'option', array( 'value' => $option['value'], 'selected' => $option['selected'] ), $option['title'] );
		}

		$args['extra']['name'] = $args['name'];

		$input = html( 'select', $args['extra'], $opts );

		return scbFormField::add_label( $input, $args['desc'], $args['desc_pos'] );
	}
}

/**
 * Radio field.
 */
class scbRadiosField extends scbSelectField {

	/**
	 * Generate the corresponding HTML for a field.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	protected function _render_specific( $args ) {

		if ( array( 'foo' ) === $args['selected'] ) {
			// radio buttons should always have one option selected
			$args['selected'] = key( $args['choices'] );
		}

		$opts = '';
		foreach ( $args['choices'] as $value => $title ) {
			$value = (string) $value;

			$single_input = scbFormField::_checkbox( array(
				'name'     => $args['name'],
				'type'     => 'radio',
				'value'    => $value,
				'checked'  => ( $value == $args['selected'] ),
				'desc'     => $title,
				'desc_pos' => 'after',
			) );

			$opts .= str_replace( scbForms::TOKEN, $single_input, $args['wrap_each'] );
		}

		return scbFormField::add_desc( $opts, $args['desc'], $args['desc_pos'] );
	}
}

/**
 * Checkbox field with multiple choices.
 */
class scbMultipleChoiceField extends scbFormField {

	/**
	 * Validates a value against a field.
	 *
	 * @param mixed $value
	 *
	 * @return array
	 */
	public function validate( $value ) {
		return array_intersect( array_keys( $this->choices ), (array) $value );
	}

	/**
	 * Generate the corresponding HTML for a field.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	protected function _render( $args ) {
		$args = wp_parse_args( $args, array(
			'numeric' => false, // use numeric array instead of associative
			'checked' => null,
		) );

		if ( ! is_array( $args['checked'] ) ) {
			$args['checked'] = array();
		}

		$opts = '';
		foreach ( $args['choices'] as $value => $title ) {
			$single_input = scbFormField::_checkbox( array(
				'name'     => $args['name'] . '[]',
				'type'     => 'checkbox',
				'value'    => $value,
				'checked'  => in_array( $value, $args['checked'] ),
				'desc'     => $title,
				'desc_pos' => 'after',
			) );

			$opts .= str_replace( scbForms::TOKEN, $single_input, $args['wrap_each'] );
		}

		return scbFormField::add_desc( $opts, $args['desc'], $args['desc_pos'] );
	}

	/**
	 * Sets value using a reference.
	 *
	 * @param array  $args
	 * @param string $value
	 *
	 * @return void
	 */
	protected function _set_value( &$args, $value ) {
		$args['checked'] = (array) $value;
	}
}

/**
 * Checkbox field.
 */
class scbSingleCheckboxField extends scbFormField {

	/**
	 * Validates a value against a field.
	 *
	 * @param mixed $value
	 *
	 * @return boolean
	 */
	public function validate( $value ) {
		return (bool) $value;
	}

	/**
	 * Generate the corresponding HTML for a field.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	protected function _render( $args ) {
		$args = wp_parse_args( $args, array(
			'value'   => true,
			'desc'    => null,
			'checked' => false,
			'extra'   => array(),
		) );

		$args['extra']['checked'] = $args['checked'];

		if ( is_null( $args['desc'] ) && ! is_bool( $args['value'] ) ) {
			$args['desc'] = str_replace( '[]', '', $args['value'] );
		}

		return scbFormField::_input_gen( $args );
	}

	/**
	 * Sets value using a reference.
	 *
	 * @param array  $args
	 * @param string $value
	 *
	 * @return void
	 */
	protected function _set_value( &$args, $value ) {
		$args['checked'] = ( $value || ( isset( $args['value'] ) && $value == $args['value'] ) );
	}
}

/**
 * Wrapper field for custom callbacks.
 */
class scbCustomField implements scbFormField_I {

	protected $args;

	/**
	 * Constructor.
	 *
	 * @param array $args
	 *
	 * @return void
	 */
	function __construct( $args ) {
		$this->args = wp_parse_args( $args, array(
			'render'   => 'var_dump',
			'sanitize' => 'wp_filter_kses',
		) );
	}

	/**
	 * Magic method: $field->arg
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		return $this->args[ $key ];
	}

	/**
	 * Magic method: isset( $field->arg )
	 *
	 * @param string $key
	 *
	 * @return boolean
	 */
	public function __isset( $key ) {
		return isset( $this->args[ $key ] );
	}

	/**
	 * Generate the corresponding HTML for a field.
	 *
	 * @param mixed $value (optional)
	 *
	 * @return string
	 */
	public function render( $value = null ) {
		return call_user_func( $this->render, $value, $this );
	}

	/**
	 * Sanitizes value.
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function validate( $value ) {
		return call_user_func( $this->sanitize, $value, $this );
	}
}

