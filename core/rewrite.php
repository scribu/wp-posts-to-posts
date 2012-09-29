<?php

class P2P_Rewrite {

	protected static $queue = array();

	function init() {
		add_action( 'p2p_registered_connection_type', array( __CLASS__, 'filter_ctypes' ), 10, 2 );

		add_action( 'template_redirect', array( __CLASS__, 'handle_endpoints' ) );
	}

	function filter_ctypes( $ctype, $args ) {
		foreach ( array( 'from', 'to' ) as $key ) {
			if ( !isset( $args[ $key . '_rewrite' ] ) )
				continue;

			$endpoint = $args[ $key . '_rewrite' ];

			if ( true === $endpoint )
				$endpoint = 'connected';

			$side = $ctype->side[ $key ];

			$rewrite_class = 'P2P_Rewrite_' . ucfirst( $side->get_object_type() );

			if ( !class_exists( $rewrite_class ) ) {
				trigger_error( sprintf( "Rewrite rules for '%s's are not implemented",
					$side->get_object_type()
				), E_USER_WARNING );
			}

			$rewrite = new $rewrite_class( $ctype->set_direction( $key ), $endpoint );
			$rewrite->add_endpoint();

			self::$queue[] = $rewrite;
		}

		return $args;
	}

	function handle_endpoints() {
		global $wp_query;

		foreach ( self::$queue as $rewrite ) {
			$rewrite->handle_endpoint( $wp_query );
		}
	}
}


class P2P_Rewrite_Post {

	protected $directed;
	protected $endpoint;

	function __construct( $directed, $endpoint ) {
		$this->directed = $directed;
		$this->endpoint = $endpoint;

		$side = $this->directed->get( 'current', 'side' );

		$this->post_types = $side->query_vars['post_type'];
	}

	function add_endpoint() {
		foreach ( $this->post_types as $post_type ) {
			$ptype = get_post_type_object( $post_type );
			if ( !$ptype->rewrite )
				continue;

			add_rewrite_endpoint( $this->endpoint, $ptype->rewrite['ep_mask'] );
		}
	}

	function handle_endpoint( $wp_query ) {
		if ( !isset( $wp_query->query_vars[ $this->endpoint ] ) )
			return;

		if ( !$wp_query->is_singular( $this->post_types ) )
			return;

		$GLOBALS['wp_query'] = $this->directed->get_connected(
			$wp_query->get_queried_object() );

		$this->post = $wp_query->get_queried_object();

		add_filter( 'template_include', array( $this, 'handle_template' ) );
		add_filter( 'wp_title', array( $this, 'handle_title' ), 10, 3 );
	}

	function handle_template( $path ) {
		$template = locate_template( 'connected.php' );
		if ( $template )
			return $template;

		return $path;
	}

	function handle_title( $title, $sep, $seplocation ) {
		$parts = array( '' );
		$parts[] = $this->post->post_title;
		$parts[] = $this->directed->get( 'current', 'title' );

		if ( 'right' == $seplocation )
			$parts = array_reverse( $parts );

		return implode( " $sep ", $parts );
	}
}

