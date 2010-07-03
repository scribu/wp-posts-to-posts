<?php

// Adds compatibility methods between WP_Widget and scbForms

abstract class scbWidget extends WP_Widget {
	protected $defaults = array();

	private static $scb_widgets = array();

	static function init( $class, $file = '', $base = '' ) {
		self::$scb_widgets[] = $class;

		add_action( 'widgets_init', array( __CLASS__, '_scb_register' ) );

		// for auto-uninstall
		if ( $file && $base && class_exists( 'scbOptions' ) )
			new scbOptions( "widget_$base", $file );
	}

	static function _scb_register() {
		foreach ( self::$scb_widgets as $widget )
			register_widget( $widget );
	}

	// A pre-filled method, for convenience
	function widget( $args, $instance ) {
		$instance = wp_parse_args( $instance, $this->defaults );

		extract( $args );

		echo $before_widget;

		$title = apply_filters( 'widget_title', isset( $instance['title'] ) ? $instance['title'] : '', $instance, $this->id_base );

		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;

		$this->content( $instance );

		echo $after_widget;
	}

	// This is where the actual widget content goes
	function content( $instance ) {}


//_____HELPER METHODS_____


	// See scbForms::input()
	// Allows extra parameter $args['title']
	protected function input( $args, $formdata = array() ) {
		// Add default class
		if ( !isset( $args['extra'] ) )
			$args['extra'] = 'class="regular-text"';

		// Add default label position
		if ( !in_array( $args['type'], array( 'checkbox', 'radio' ) ) && empty( $args['desc_pos'] ) )
			$args['desc_pos'] = 'before';

		// Then add prefix to names and formdata
		$new_formdata = array();
		foreach ( ( array ) $args['name'] as $name )
			$new_formdata[$this->scb_get_field_name( $name )] = @$formdata[$name];
		$new_names = array_keys( $new_formdata );

		// Finally, replace the old names
		if ( 1 == count( $new_names ) )
			$args['name'] = $new_names[0];
		else
			$args['name'] = $new_names;

		// Remember $desc and replace with $title
		$desc = '';
		if ( isset( $args['title'] ) ) {
			if ( isset( $args['desc'] ) )
				$desc = "<small>{$args['desc']}</small>";
			$args['desc'] = $args['title'];
			unset( $args['title'] );
		}

		$input = scbForms::input( $args, $new_formdata );

		return "<p>{$input}\n<br />\n$desc\n</p>\n";
	}


//_____INTERNAL METHODS_____


	private function scb_get_field_name( $name ) {
		if ( $split = scbUtil::split_at( '[', $name ) )
			list( $basename, $extra ) = $split;
		else
			return $this->get_field_name( $name );

		return str_replace( '[]', '', $this->get_field_name( $basename ) ) . $extra;
	}
}

