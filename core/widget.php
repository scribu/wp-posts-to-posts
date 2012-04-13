<?php

class P2P_Widget extends scbWidget {

	protected $defaults = array(
		'ctype' => false,
		'listing' => 'connected',
		'title' => ''
	);

	static function init() {
		parent::init( __CLASS__, false, 'p2p' );
	}

	function __construct() {
		parent::__construct( 'p2p', __( 'Posts 2 Posts', P2P_TEXTDOMAIN ), array(
			'description' => __( 'A list of posts connected to the current post', P2P_TEXTDOMAIN )
		) );
	}

	function form( $instance ) {
		if ( empty( $instance ) )
			$instance = $this->defaults;

		$ctypes = array();

		foreach ( P2P_Connection_Type_Factory::get_all_instances() as $p2p_type => $ctype ) {
			if ( ! $ctype instanceof P2P_Connection_Type )
				continue;

			$ctypes[ $p2p_type ] = $ctype->get_desc();
		}

		echo html( 'p', $this->input( array(
			'type' => 'text',
			'name' => 'title',
			'desc' => __( 'Title:', P2P_TEXTDOMAIN )
		), $instance ) );

		echo html( 'p', $this->input( array(
			'type' => 'select',
			'name' => 'ctype',
			'values' => $ctypes,
			'desc' => __( 'Connection type:', P2P_TEXTDOMAIN )
		), $instance ) );

		echo html( 'p',
			__( 'Connection listing:', P2P_TEXTDOMAIN ),
			'<br>',
			$this->input( array(
				'type' => 'radio',
				'name' => 'listing',
				'values' => array(
					'connected' => __( 'connected', P2P_TEXTDOMAIN ),
					'related' => __( 'related', P2P_TEXTDOMAIN )
				),
			), $instance )
		);
	}

	function widget( $args, $instance ) {
		if ( !is_singular() )
			return;

		$instance = array_merge( $this->defaults, $instance );

		$post = get_queried_object();

		$ctype = p2p_type( $instance['ctype'] );
		if ( !$ctype )
			return;

		$directed = $ctype->find_direction( $post );
		if ( !$directed )
			return;

		$extra_qv = array( 'p2p:context' => 'widget' );

		if ( 'related' == $instance['listing'] ) {
			$method = 'get_related';
		} else {
			$method = 'get_connected';
		}

		$connected = $directed->$method( $post, $extra_qv, 'abstract' );

		if ( empty( $connected->items ) )
			return;

		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		extract( $args );

		echo $before_widget;

		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;

		$args = array(
			'before_list' => '<ul id="' . $ctype->name . '_list">',
			'echo' => false
		);

		echo apply_filters( 'p2p_widget_html', $connected->render( $args ), $connected, $directed, $instance );

		echo $after_widget;
	}
}

