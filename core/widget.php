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
			'desc' => __( 'Connection type:', P2P_TEXTDOMAIN ),
			'extra' => "style='width: 100%'"
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
		$instance = array_merge( $this->defaults, $instance );

		$output = P2P_List_Renderer::query_and_render( array(
			'ctype' => $instance['ctype'],
			'method' => ( 'related' == $instance['listing'] ? 'get_related' : 'get_connected' ),
			'item' => get_queried_object(),
			'mode' => 'ul',
			'context' => 'widget'
		) );

		if ( !$output )
			return;

		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		extract( $args );

		echo $before_widget;

		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;

		echo $output;

		echo $after_widget;
	}
}

