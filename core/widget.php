<?php

class P2P_Widget extends scbWidget {

	protected $defaults = array(
		'ctype' => false,
	);

	static function init( $file ) {
		parent::init( __CLASS__, $file, 'p2p' );
	}

	function __construct() {
		parent::__construct( 'p2p', __( 'Posts 2 Posts', P2P_TEXTDOMAIN ), array(
			'description' => __( 'Display a list of connected posts', P2P_TEXTDOMAIN )
		) );
	}

	function form( $instance ) {
		if ( empty( $instance ) )
			$instance = $this->defaults;

		$ctypes = array_map( array( __CLASS__, 'ctype_label' ), P2P_Connection_Type::$instances );

		echo $this->input( array(
			'type' => 'select',
			'name' => 'ctype',
			'values' => $ctypes,
		), $instance );
	}

	function widget( $args, $instance ) {
		if ( !is_singular() )
			return;

		$ctype = P2P_Connection_Type::get_instance( $instance['ctype'] );
		if ( !$ctype )
			return;

		$direction = $ctype->get_direction( get_queried_object_id(), true );
		if ( !$direction )
			return;

		$connected = $ctype->get_connected( get_queried_object_id() );
		if ( !$connected->have_posts() )
			return;

		$title = $ctype->get_title( $direction );

		if ( empty( $title ) ) {
			$ptype = get_post_type_object( $ctype->get_other_post_type( $direction ) );
			$title = sprintf( __( 'Related %s', P2P_TEXTDOMAIN ), $ptype->label );
		}

		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		extract( $args );

		echo $before_widget;

		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;

		p2p_list_posts( $connected );

		echo $after_widget;
	}

	private static function ctype_label( $ctype ) {
		foreach ( array( 'from', 'to' ) as $key ) {
			$$key = implode( ', ', array_map( array( __CLASS__, 'cpt_label' ), $ctype->$key ) );
		}

		if ( $ctype->reciprocal || $ctype->to == $ctype->from )
			$arrow = '&harr;';
		else
			$arrow = '&rarr;';

		$label = "$from $arrow $to";

		$title = $ctype->get_title( 'from' );

		if ( $title )
			$label .= " ($title)";

		return $label;
	}

	private static function cpt_label( $post_type ) {
		return get_post_type_object( $post_type )->label;
	}
}

