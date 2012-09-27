<?php

class P2P_Rewrite {

	protected static $queue = array();

	function init() {
		add_filter( 'p2p_connection_type_args', array( __CLASS__, 'filter_ctypes' ), 10, 2 );

		add_action( 'template_redirect', array( __CLASS__, 'handle_endpoints' ) );
	}

	function filter_ctypes( $args, $sides ) {
		foreach ( array( 'from', 'to' ) as $key ) {
			if ( !isset( $args[ $key . '_rewrite' ] ) )
				continue;

			$endpoint = $args[ $key . '_rewrite' ];

			if ( true === $endpoint )
				$endpoint = 'connected';

			$side = $sides[ $key ];

			$rewrite_class = 'P2P_Rewrite_' . ucfirst( $side->get_object_type() );

			if ( !class_exists( $rewrite_class ) ) {
				trigger_error( sprintf( "Rewrite rules for '%s's are not implemented",
					$side->get_object_type()
				), E_USER_WARNING );
			}

			$rewrite = new $rewrite_class( $args['name'], $side, $endpoint );
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

	protected $ctype_name;
	protected $side;
	protected $endpoint;

	function __construct( $ctype, $side, $endpoint ) {
		$this->ctype_name = $ctype;
		$this->endpoint = $endpoint;
		$this->side = $side;
	}

	function add_endpoint() {
		foreach ( $this->side->query_vars['post_type'] as $post_type ) {
			$ptype = get_post_type_object( $post_type );
			if ( !$ptype->rewrite )
				continue;

			add_rewrite_endpoint( $this->endpoint, $ptype->rewrite['ep_mask'] );
		}
	}

	function handle_endpoint( $wp_query ) {
		if ( !isset( $wp_query->query_vars[ $this->endpoint ] ) )
			return;

		if ( !$wp_query->is_singular( $this->side->query_vars['post_type'] ) )
			return;

		query_posts( array(
			'connected_type' => $this->ctype_name,
			'connected_items' => $wp_query->get_queried_object(),
		) );
	}
}

