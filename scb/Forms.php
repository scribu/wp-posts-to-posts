<?php

// Documentation: http://scribu.net/wordpress/scb-framework/scb-forms.html

class scbForms {

	const token = '%input%';

	protected static $args;
	protected static $formdata = array();

	static function input( $args, $formdata = array() ) {
		$args = self::validate_data( $args );

		$error = false;
		foreach ( array( 'name', 'value' ) as $key ) {
			$old = $key . 's';

			if ( isset( $args[$old] ) ) {
				$args[$key] = $args[$old];
				unset( $args[$old] );
			}
		}

		if ( empty( $args['name'] ) )
			return trigger_error( 'Empty name', E_USER_WARNING );

		self::$args = $args;
		self::$formdata = self::validate_data( $formdata );

		if ( 'select' == $args['type'] )
			return self::_select();
		else
			return self::_input();
	}


// ____________UTILITIES____________


	// Generates a table wrapped in a form
	static function form_table( $rows, $formdata = NULL ) {
		$output = '';
		foreach ( $rows as $row )
			$output .= self::table_row( $row, $formdata );

		$output = self::form_table_wrap( $output );

		return $output;
	}

	// Generates a form
	static function form( $inputs, $formdata = NULL, $nonce ) {
		$output = '';
		foreach ( $inputs as $input )
			$output .= self::input( $input, $formdata );

		$output = self::form_wrap( $output, $nonce );

		return $output;
	}

	// Generates a table
	static function table( $rows, $formdata = NULL ) {
		$output = '';
		foreach ( $rows as $row )
			$output .= self::table_row( $row, $formdata );

		$output = self::table_wrap( $output );

		return $output;
	}

	// Generates a table row
	static function table_row( $args, $formdata = NULL ) {
		return self::row_wrap( $args['title'], self::input( $args, $formdata ) );
	}


// ____________WRAPPERS____________


	// Wraps the given content in a <form><table>
	static function form_table_wrap( $content, $nonce = 'update_options' ) {
		$output = self::table_wrap( $content );
		$output = self::form_wrap( $output, $nonce );

		return $output;
	}

	// Wraps the given content in a <form> tag
	static function form_wrap( $content, $nonce = 'update_options' ) {
		$output = "\n<form method='post' action=''>\n";
		$output .= $content;
		$output .= wp_nonce_field( $action = $nonce, $name = "_wpnonce", $referer = true , $echo = false );
		$output .= "\n</form>\n";

		return $output;
	}

	// Wraps the given content in a <table>
	static function table_wrap( $content ) {
		$output = "\n<table class='form-table'>\n" . $content . "\n</table>\n";

		return $output;
	}

	// Wraps the given content in a <tr><td>
	static function row_wrap( $title, $content ) {
		return "\n<tr>\n\t<th scope='row'>" . $title . "</th>\n\t<td>\n\t\t" . $content . "\t</td>\n\n</tr>";
	}


// ____________PRIVATE METHODS____________


	// Recursivly transform empty arrays to ''
	private static function validate_data( $data ) {
		if ( !is_array( $data ) )
			return $data;

		if ( empty( $data ) )
			return '';

		foreach ( $data as $key => &$value )
			$value = self::validate_data( $value );

		return $data;
	}

	// From multiple inputs to single inputs
	private static function _input() {
		extract( wp_parse_args( self::$args, array( 
			'name' => NULL,
			'value' => NULL,
			'desc' => NULL,
			'checked' => NULL,
		) ) );

		$m_name = is_array( $name );
		$m_value = is_array( $value );
		$m_desc = is_array( $desc );

		// Correct name
		if ( !$m_name && $m_value
			&& 'checkbox' == $type
			&& false === strpos( $name, '[' )
		)
			$args['name'] = $name = $name . '[]';

		// Expand names or values
		if ( !$m_name && !$m_value ) {
			$a = array( $name => $value );
		}
		elseif ( $m_name && !$m_value ) {
			$a = array_fill_keys( $name, $value );
		}
		elseif ( !$m_name && $m_value ) {
			$a = array_fill_keys( $value, $name );
		}
		else {
			$a = array_combine( $name, $value );
		}

		// Correct descriptions
		$_after = '';
		if ( isset( $desc ) && !$m_desc && false === strpos( $desc, self::token ) ) {
			if ( $m_value ) {
				$_after = $desc;
				$args['desc'] = $desc = $value;
			}
			elseif ( $m_name ) {
				$_after = $desc;
				$args['desc'] = $desc = $name;			
			}
		}

		// Determine what goes where
		if ( !$m_name && $m_value ) {
			$i1 = 'val';
			$i2 = 'name';
		} else {
			$i1 = 'name';
			$i2 = 'val';
		}

		$func = in_array( $type, array( 'checkbox', 'radio' ) ) ? '_checkbox_single' : '_input_single';

		// Set constant args
		$const_args = self::array_extract( self::$args, array( 'type', 'desc_pos', 'checked' ) );
		if ( isset( $extra ) ) {
			if ( !is_array( $extra ) )
				$extra = self::attr_to_array( $extra );
			$const_args['extra'] = $extra;
		}

		$i = 0;
		foreach ( $a as $name => $val ) {
			$cur_args = $const_args;

			if ( $$i1 !== NULL )
				$cur_args['name'] = $$i1;

			if ( $$i2 !== NULL )
				$cur_args['value'] = $$i2;

			// Set desc
			if ( is_array( $desc ) )
				$cur_args['desc'] = $desc[$i];
			elseif ( isset( $desc ) )
				$cur_args['desc'] = $desc;

			// Find relevant formdata
			$match = NULL;
			if ( $checked === NULL ) {
				$key = str_replace( '[]', '', $$i1 );

				if ( isset( self::$formdata[ $key ] ) ) {
					$match = self::$formdata[ $key ];

					if ( is_array( $match ) ) {
						$match = $match[$i];
					}
				}
			} else if ( is_array( $checked ) ) {
				$cur_args['checked'] = isset( $checked[$i] ) && $checked[$i];
			}

			$output[] = self::$func( $cur_args, $match );

			$i++;
		}

		return implode( "\n", $output ) . $_after;
	}

	// Handle args for checkboxes and radio inputs
	private static function _checkbox_single( $args, $data ) {
		$args = wp_parse_args( $args, array( 
			'name' => NULL,
			'value' => true,
			'desc_pos' => 'after',
			'desc' => NULL,
			'checked' => NULL,
			'extra' => array(),
		) );

		foreach ( $args as $key => &$val )
			$$key = &$val;
		unset( $val );

		if ( $checked === NULL && $value == $data )
			$checked = true;

		if ( $checked )
			$extra['checked'] = 'checked';

		if ( is_null( $desc ) && !is_bool( $value ) )
			$desc = str_replace( '[]', '', $value );

		return self::_input_gen( $args );
	}

	// Handle args for text inputs
	private static function _input_single( $args, $data ) {
		$args = wp_parse_args( $args, array( 
			'value' => $data,
			'desc_pos' => 'after',
			'extra' => array( 'class' => 'regular-text' ),
		) );

		foreach ( $args as $key => &$val )
			$$key = &$val;
		unset( $val );

		if ( FALSE === strpos( $name, '[' ) )
			$extra['id'] = $name;

		return self::_input_gen( $args );
	}

	// Generate html with the final args
	private static function _input_gen( $args ) {
		extract( wp_parse_args( $args, array( 
			'name' => NULL,
			'value' => NULL,
			'desc' => NULL,
			'extra' => array()
		) ) );

		$extra = self::array_to_attr( $extra );

		if ( 'textarea' == $type ) {
			$value = esc_html( $value );
			$input = "<textarea name='{$name}'{$extra}>{$value}</textarea>\n";
		}
		else {
			$value = esc_attr( $value );
			$input = "<input name='{$name}' value='{$value}' type='{$type}'{$extra} /> ";
		}

		return self::add_label( $input, $desc, $desc_pos );
	}

	private static function _select() {
		extract( wp_parse_args( self::$args, array( 
			'name' => '',
			'value' => array(),
			'text' => '',
			'selected' => array( 'foo' ),	// hack to make default blank
			'extra' => array(),
			'numeric' => false,	// use numeric array instead of associative
			'desc' => '',
			'desc_pos' => '',
		) ), EXTR_SKIP );

		if ( empty( $value ) )
			$value = array( '' => '' );

		if ( !is_array( $value ) )
			return trigger_error( "'value' argument is expected to be an array", E_USER_WARNING );

		if ( !self::is_associative( $value ) && !$numeric )
			$value = array_combine( $value, $value );

		if ( isset( self::$formdata[$name] ) )
			$cur_val = self::$formdata[$name];
		else
			$cur_val = $selected;

		if ( false === $text ) {
			$opts = '';
		} else {
			$opts = "\t<option value=''" . selected( $cur_val, array( 'foo' ), false ) . ">{$text}</option>\n";
		}

		foreach ( $value as $key => $value ) {
			if ( empty( $key ) || empty( $value ) )
				continue;

			$opts .= "\t<option value='{$key}'" . selected( (string) $key, (string) $cur_val, false) . '>' . $value . "</option>\n";
		}

		if ( !is_array( $extra ) )
			$extra = self::attr_to_array( $extra );
		$extra = self::array_to_attr( $extra );

		$input =  "<select name='{$name}'$extra>\n{$opts}</select>";
		
		return self::add_label( $input, $desc, $desc_pos );
	}

	private static function add_label( $input, $desc, $desc_pos ) {
		if ( empty( $desc_pos ) )
			$desc_pos = 'after';

		$label = '';
		if ( false === strpos( $desc, self::token ) ) {
			switch ( $desc_pos ) {
				case 'before': $label = $desc . ' ' . self::token; break;
				case 'after': $label = self::token . ' ' . $desc;
			}
		} else {
			$label = $desc;
		}

		$label = trim( str_replace( self::token, $input, $label ) );

		if ( empty( $desc ) )
			$output = $input . "\n";
		else
			$output = "<label>{$label}</label>\n";

		return $output;
	}


// Utilities


	private static function attr_to_array( $html ) {
		return shortcode_parse_atts( $html );
	}

	private static function array_to_attr( $attr ) {
		$attr = array_filter( (array) $attr );

		$out = '';
		foreach ( $attr as $key => $value )
			$out .= ' ' . $key . '=' . '"' . esc_attr( $value ) . '"';

		return $out;
	}

	private static function is_associative( $array ) {
		if ( !is_array( $array ) || empty( $array ) )
			return false;

		$keys = array_keys( $array );

		return array_keys( $keys ) !== $keys;
	}

	private static function array_extract( $array, $keys ) {
		$r = array();
		foreach ( $keys as $key )
			if ( isset( $array[$key] ) )
				$r[$key] = $array[$key];

		return $r;
	}
}

// PHP < 5.2
if ( !function_exists( 'array_fill_keys' ) ) :
function array_fill_keys( $keys, $value ) {
	if ( !is_array( $keys ) )
		trigger_error( 'First argument is expected to be an array.' . gettype( $keys ) . 'given', E_USER_WARNING );

	$r = array();
	foreach ( $keys as $key )
		$r[$key] = $value;

	return $r;
}
endif;

