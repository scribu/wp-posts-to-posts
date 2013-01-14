<?php

WP_CLI::add_command( 'p2p', 'P2P_CLI_Command' );

class P2P_CLI_Command extends WP_CLI_Command {

	/**
	 * List registered connection types.
	 *
	 * @subcommand connection-types
	 */
	function connection_types() {
		foreach ( P2P_Connection_Type_Factory::get_all_instances() as $p2p_type => $ctype ) {
			WP_CLI::line( $p2p_type );
		}
	}

	/**
	 * Generate connections for a specific connection type.
	 *
	 * @subcommand generate-connections
	 * @synopsis <connection-type> [--items]
	 */
	function generate_connections( $args, $assoc_args ) {
		list( $connection_type ) = $args;

		$ctype = p2p_type( $connection_type );
		if ( !$ctype )
			WP_CLI::error( "'$connection_type' is not a registered connection type." );

		if ( isset( $assoc_args['items'] ) ) {
			foreach ( _p2p_extract_post_types( $ctype->side ) as $ptype ) {
				$assoc_args = array( 'post_type' => $ptype );

				WP_CLI::launch( 'wp post generate' . \WP_CLI\Utils\assoc_args_to_str( $assoc_args ) );
			}
		}

		$count = $this->_generate_c( $ctype );

		WP_CLI::success( "Created $count connections." );
	}

	private function _generate_c( $ctype ) {
		$extra_qv = array( 'p2p:per_page' => 10 );

		$candidate = $ctype
			->set_direction( 'from' )
			->get_connectable( 'any', $extra_qv, 'abstract' );

		$count = 0;

		foreach ( $candidate->items as $from ) {
			$eligible = $ctype->get_connectable( $from, array(
				'p2p:per_page' => rand( 0, 5 )
			), 'abstract' );

			foreach ( $eligible->items as $to ) {
				$r = $ctype->connect( $from, $to );

				if ( is_wp_error( $r ) )
					WP_CLI::warning( $r );
				else
					$count++;
			}
		}

		return $count;
	}

	/**
	 * Set up the example connections.
	 *
	 * @subcommand setup-example
	 */
	function setup_example() {
		$ctype = p2p_type( 'actor_movie' );

		$data = array(
			'Nicholas Cage' => array( 'Lord Of War', 'Adaptation' ),
			'Jude Law' => array( 'Sherlock Holmes' ),
			'Brad Pitt' => array( '7 Years In Tibet', 'Fight Club' ),
			'Natalie Portman' => array( 'Black Swan', 'Thor' ),
			'Matt Damon' => array( 'The Talented Mr. Ripley' ),
			'Charlize Theron' => array(),
		);

		foreach ( $data as $actor_name => $movies ) {
			$actor = self::titled_post( 'actor',  $actor_name );

			foreach ( $movies as $movie_title ) {
				$movie = self::titled_post( 'movie', $movie_title );

				$ctype->connect( $actor, $movie );
			}
		}

		WP_CLI::success( "Set up the actors and movies example." );
	}

	private static function titled_post( $type, $title ) {
		return wp_insert_post( array(
			'post_type' => $type,
			'post_title' => $title,
			'post_status' => 'publish'
		) );
	}
}

