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
			'description' => __( 'A list of posts connected to the current post', P2P_TEXTDOMAIN )
		) );
	}

	function form( $instance ) {
		if ( empty( $instance ) )
			$instance = $this->defaults;

		$ctypes = array_map( array( __CLASS__, 'ctype_label' ), P2P_Connection_Type::get() );

		echo $this->input( array(
			'type' => 'select',
			'name' => 'ctype',
			'values' => $ctypes,
			'desc' => __( 'Connection type:', P2P_TEXTDOMAIN )
		), $instance );
	}

	function widget( $args, $instance ) {
		if ( !is_singular() )
			return;

		$ctype = P2P_Connection_Type::get_instance( $instance['ctype'] );
		if ( !$ctype )
			return;

		$directed = $ctype->find_direction( get_queried_object_id() );
		if ( !$directed )
			return;

		$connected = $directed->get_connected( get_queried_object_id() );
		if ( !$connected->have_posts() )
			return;

		$title = $directed->get_title();

		if ( empty( $title ) ) {
			$ptype = get_post_type_object( $directed->get_other_post_type() );
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
			$$key = implode( ', ', array_map( array( __CLASS__, 'post_type_label' ), $ctype->$key ) );
		}

		$directed = $ctype->find_direction( $ctype->from[0] );

		if ( 'any' == $directed->direction )
			$arrow = '&harr;';
		else
			$arrow = '&rarr;';

		$label = "$from $arrow $to";

		$title = $directed->get_title();

		if ( $title )
			$label .= " ($title)";

		return $label;
	}

	private static function post_type_label( $post_type ) {
		$cpt = get_post_type_object( $post_type );
		return $cpt ? $cpt->label : $post_type;
	}
}

