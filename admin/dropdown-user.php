<?php

class P2P_Dropdown_User extends P2P_Dropdown_Post {

	function __construct( $directed, $title ) {
		parent::__construct( $directed, $title );

		add_action( 'pre_user_query', array( __CLASS__, 'massage_query' ), 9 );

		add_action( 'restrict_manage_users', array( $this, 'show_dropdown' ) );
	}

	static function massage_query( $query ) {
		if ( isset( $query->_p2p_capture ) )
			return;

		// Don't overwrite existing P2P query
		if ( isset( $query->query_vars['connected_type'] ) )
			return;

		_p2p_append( $query->query_vars, self::get_qv() );
	}

	protected function render_dropdown() {
		return html( 'div', array(
			'style' => 'float: right; margin-left: 16px'
		),
			parent::render_dropdown(),
			html( 'input', array(
				'type' => 'submit',
				'class' => 'button',
				'value' => __( 'Filter', P2P_TEXTDOMAIN )
			) )
		);
	}
}

