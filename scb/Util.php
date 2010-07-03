<?php

class scbUtil {

	// Force script enqueue
	static function do_scripts( $handles ) {
		global $wp_scripts;

		if ( ! is_a( $wp_scripts, 'WP_Scripts' ) )
			$wp_scripts = new WP_Scripts();

		$wp_scripts->do_items( ( array ) $handles );
	}

	// Force style enqueue
	static function do_styles( $handles ) {
		self::do_scripts( 'jquery' );

		global $wp_styles;

		if ( ! is_a( $wp_styles, 'WP_Styles' ) )
			$wp_styles = new WP_Styles();

		ob_start();
		$wp_styles->do_items( ( array ) $handles );
		$content = str_replace( array( '"', "\n" ), array( "'", '' ), ob_get_clean() );

		echo "<script type='text/javascript'>\n";
		echo "jQuery( document ).ready( function( $ ) {\n";
		echo "$( 'head' ).prepend( \"$content\" );\n";
		echo "} );\n";
		echo "</script>";
	}

	// Enable delayed activation ( to be used with scb_init() )
	static function add_activation_hook( $plugin, $callback ) {
		add_action( 'scb_activation_' . plugin_basename( $plugin ), $callback );
	}

	// Have more than one uninstall hooks; also prevents an UPDATE query on each page load
	static function add_uninstall_hook( $plugin, $callback ) {
		register_uninstall_hook( $plugin, '__return_false' );	// dummy

		add_action( 'uninstall_' . plugin_basename( $plugin ), $callback );
	}

	// Apply a function to each element of a ( nested ) array recursively
	static function array_map_recursive( $callback, $array ) {
		array_walk_recursive( $array, array( __CLASS__, 'array_map_recursive_helper' ), $callback );

		return $array;
	}

	static function array_map_recursive_helper( &$val, $key, $callback ) {
		$val = call_user_func( $callback, $val );
	}

	// Extract certain $keys from $array
	static function array_extract( $array, $keys ) {
		$r = array();

		foreach ( $keys as $key )
			if ( array_key_exists( $key, $array ) )
				$r[$key] = $array[$key];

		return $r;
	}

	// Extract a certain value from a list of arrays
	static function array_pluck( $array, $key ) {
		$r = array();

		foreach ( $array as $value ) {
			if ( is_object( $value ) )
				$value = get_object_vars( $value );
			if ( array_key_exists( $key, $value ) )
				$r[] = $value[$key];
		}

		return $r;
	}

	// Transform a list of objects into an associative array
	static function objects_to_assoc( $objects, $key, $value ) {
		$r = array();

		foreach ( $objects as $obj )
			$r[$obj->$key] = $obj->$value;

		return $r;
	}

	// Prepare an array for an IN statement
	static function array_to_sql( $values ) {
		foreach ( $values as &$val )
			$val = "'" . esc_sql( trim( $val ) ) . "'";

		return implode( ',', $values );
	}

	// Example: split_at( '</', '<a></a>' ) => array( '<a>', '</a>' )
	static function split_at( $delim, $str ) {
		$i = strpos( $str, $delim );

		if ( false === $i )
			return false;

		$start = substr( $str, 0, $i );
		$finish = substr( $str, $i );

		return array( $start, $finish );
	}
}


//_____Minimalist HTML framework_____


if ( ! function_exists( 'html' ) ):
function html( $tag, $content = '' ) {
	list( $closing ) = explode( ' ', $tag, 2 );

	return "<{$tag}>{$content}</{$closing}>";
}
endif;

// Generate an <a> tag
if ( ! function_exists( 'html_link' ) ):
function html_link( $url, $title = '' ) {
	if ( empty( $title ) )
		$title = $url;

	return sprintf( "<a href='%s'>%s</a>", esc_url( $url ), $title );
}
endif;


//_____Compatibility layer_____


// WP < 3.0
if ( ! function_exists( '__return_false' ) ) :
function __return_false() {
	return false;
}
endif;

// WP < ?
if ( ! function_exists( '__return_true' ) ) :
function __return_true() {
	return true;
}
endif;

// WP < ?
if ( ! function_exists( 'set_post_field' ) ) :
function set_post_field( $field, $value, $post_id ) {
	global $wpdb;

	$post_id = absint( $post_id );
	$value = sanitize_post_field( $field, $value, $post_id, 'db' );

	return $wpdb->update( $wpdb->posts, array( $field => $value ), array( 'ID' => $post_id ) );
}
endif;

