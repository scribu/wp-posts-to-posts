<?php

// Documentation: http://scribu.net/wordpress/scb-framework/scb-forms.html

class scbForms {

	const token = '%input%';

	protected static $args;
	protected static $formdata = array();

	static function input($args, $formdata = array()) {
		$args = self::validate_data($args);

		$error = false;
		foreach ( array('name', 'value') as $key ) {
			$old = $key . 's';

			if ( isset($args[$old]) ) {
				$args[$key] = $args[$old];
				unset($args[$old]);
			}
		}

		if ( empty($args['name']) )
			return trigger_error('Empty name', E_USER_WARNING);

		self::$args = $args;
		self::$formdata = self::validate_data($formdata);

		if ( 'select' == $args['type'] )
			return self::_select();
		else
			return self::_input();
	}


	// Generates a form
	static function form($inputs, $formdata = NULL, $nonce) {
		$output = '';
		foreach ( $inputs as $input )
			$output .= self::input($input, $formdata);

		$output = self::form_wrap($output, $nonce);

		return $output;
	}

	// Wraps the given content in a <form> tag
	static function form_wrap($content, $nonce = 'update_options') {
		$output = "\n<form method='post' action=''>\n";
		$output .= $content;
		$output .= wp_nonce_field($action = $nonce, $name = "_wpnonce", $referer = true , $echo = false);
		$output .= "\n</form>\n";

		return $output;
	}


// ____________PRIVATE METHODS____________


	// Recursivly transform empty arrays to ''
	private static function validate_data($data) {
		if ( !is_array($data) )
			return $data;

		if ( empty($data) )
			return '';

		foreach ( $data as $key => &$value )
			$value = self::validate_data($value);

		return $data;
	}

	// From multiple inputs to single inputs
	private static function _input() {
		extract(wp_parse_args(self::$args, array(
			'name' => NULL,
			'value' => NULL,
			'desc' => NULL,
			'checked' => NULL,
		)));

		$m_name = is_array($name);
		$m_value = is_array($value);
		$m_desc = is_array($desc);

		// Correct name
		if ( !$m_name && $m_value
			&& 'checkbox' == $type
			&& false === strpos($name, '[')
		)
			$args['name'] = $name = $name . '[]';

		// Expand names or values
		if ( !$m_name && !$m_value ) {
			$a = array($name => $value);
		}
		elseif ( $m_name && !$m_value ) {
			$a = array_fill_keys($name, $value);
		}
		elseif ( !$m_name && $m_value ) {
			$a = array_fill_keys($value, $name);
		}
		else {
			$a = array_combine($name, $value);
		}

		// Correct descriptions
		$_after = '';
		if ( isset($desc) && !$m_desc && false === strpos($desc, self::token) ) {
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

		$func = in_array($type, array('checkbox', 'radio')) ? '_checkbox_single' : '_input_single';

		// Set constant args
		$const_args = self::array_extract(self::$args, array('type', 'desc_pos', 'checked'));
		if ( isset($extra) )
			$const_args['extra'] = explode(' ', $extra);

		$i = 0;
		foreach ( $a as $name => $val ) {
			$cur_args = $const_args;

			if ( $$i1 !== NULL )
				$cur_args['name'] = $$i1;

			if ( $$i2 !== NULL )
				$cur_args['value'] = $$i2;

			// Set desc
			if ( is_array($desc) )
				$cur_args['desc'] = $desc[$i];
			elseif ( isset($desc) )
				$cur_args['desc'] = $desc;

			// Find relevant formdata
			$match = NULL;
			if ( $checked === NULL ) {
				$match = @self::$formdata[str_replace('[]', '', $$i1)];
				if ( is_array($match) ) {
					$match = $match[$i];
				}
			} else if ( is_array($checked) ) {
				$cur_args['checked'] = isset($checked[$i]) && $checked[$i];
			}

			$output[] = self::$func($cur_args, $match);

			$i++;
		}

		return implode("\n", $output) . $_after;
	}

	// Handle args for checkboxes and radio inputs
	private static function _checkbox_single($args, $data) {
		$args = wp_parse_args($args, array(
			'name' => NULL,
			'value' => true,
			'desc_pos' => 'after',
			'desc' => NULL,
			'checked' => NULL,
			'extra' => array(),
		));

		foreach ( $args as $key => &$val )
			$$key = &$val;
		unset($val);

		if ( $checked === NULL && $value == $data )
			$checked = true;

		if ( $checked )
			$extra[] = 'checked="checked"';

		if ( $desc === NULL && !is_bool($value) )
			$desc = str_replace('[]', '', $value);

		return self::_input_gen($args);
	}

	// Handle args for text inputs
	private static function _input_single($args, $data) {
		$args = wp_parse_args($args, array(
			'value' => $data,
			'desc_pos' => 'after',
			'extra' => array('class="regular-text"'),
		));

		foreach ( $args as $key => &$val )
			$$key = &$val;
		unset($val);

		if ( FALSE === strpos($name, '[') )
			$extra[] = "id='{$name}'";

		return self::_input_gen($args);
	}

	// Generate html with the final args
	private static function _input_gen($args) {
		extract(wp_parse_args($args, array(
			'name' => NULL,
			'value' => NULL,
			'desc' => NULL,
			'extra' => array()
		)));

		$extra = self::validate_extra($extra, $name);

		if ( 'textarea' == $type ) {
			$value = esc_html($value);
			$input = "<textarea name='{$name}'{$extra}>\n{$value}\n</textarea>\n";
		}
		else {
			$value = esc_attr($value);
			$input = "<input name='{$name}' value='{$value}' type='{$type}'{$extra} /> ";
		}

		return self::add_label($input, $desc, $desc_pos);
	}

	private static function _select() {
		extract(wp_parse_args(self::$args, array(
			'name' => '',
			'value' => array(),
			'text' => '',
			'selected' => array('foo'),	// hack to make default blank
			'extra' => '',
			'numeric' => false,	// use numeric array instead of associative
			'desc' => '',
			'desc_pos' => '',
		)), EXTR_SKIP);

		if ( empty($value) )
			$value = array('' => '');

		if ( !is_array($value) )
			return trigger_error("'value' argument is expected to be an array", E_USER_WARNING);

		if ( !self::is_associative($value) && !$numeric )
			$value = array_combine($value, $value);

		if ( isset(self::$formdata[$name]) )
			$cur_val = self::$formdata[$name];
		else
			$cur_val = $selected;

		if ( false === $text ) {
			$opts = '';
		} else {
			$opts = "\t<option value=''";
			if ( $cur_val === array('foo') )
				$opts .= " selected='selected'";
			$opts .= ">{$text}</option>\n";
		}

		foreach ( $value as $key => $value ) {
			if ( empty($key) || empty($value) )
				continue;

			$cur_extra = array();
			if ( (string) $key == (string) $cur_val )
				$cur_extra[] = "selected='selected'";

			$cur_extra = self::validate_extra($cur_extra, $key);

			$opts .= "\t<option value='{$key}'{$cur_extra}>{$value}</option>\n";
		}

		$extra = self::validate_extra($extra, $name);

		$input =  "<select name='{$name}'$extra>\n{$opts}</select>";
		
		return self::add_label($input, $desc, $desc_pos);
	}

	private static function add_label($input, $desc, $desc_pos) {
		if ( empty($desc_pos) )
			$desc_pos = 'after';

		$label = '';
		if ( false === strpos($desc, self::token) ) {
			switch ($desc_pos) {
				case 'before': $label = $desc . ' ' . self::token; break;
				case 'after': $label = self::token . ' ' . $desc;
			}
		} else {
			$label = $desc;
		}

		$label = trim(str_replace(self::token, $input, $label));

		if ( empty($desc) )
			$output = $input . "\n";
		else
			$output = "<label>{$label}</label>\n";

		return $output;
	}

	private static function validate_extra($extra, $name, $implode = true) {
		if ( !is_array($extra) )
			$extra = explode(' ', $extra);

		if ( empty($extra) )
			return '';

		return ' ' . ltrim(implode(' ', $extra));
	}

// Utilities

	private static function is_associative($array) {
		if ( !is_array($array) || empty($array) )
			return false;

		$keys = array_keys($array);

		return array_keys($keys) !== $keys;
	}

	private static function array_extract($array, $keys) {
		$r = array();
		foreach ( $keys as $key )
			if ( isset($array[$key]) )
				$r[$key] = $array[$key];

		return $r;
	}
}

// PHP < 5.2
if ( !function_exists('array_fill_keys') ) :
function array_fill_keys($keys, $value) {
	if ( !is_array($keys) )
		trigger_error('First argument is expected to be an array.' . gettype($keys) . 'given', E_USER_WARNING);

	$r = array();
	foreach ( $keys as $key )
		$r[$key] = $value;

	return $r;
}
endif;

