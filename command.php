<?php

WP_CLI::add_command( 'p2p', 'P2P_CLI_Command' );

class P2P_CLI_Command extends WP_CLI_Command {

	function connection_types() {
		foreach ( P2P_Connection_Type_Factory::get_all_instances() as $p2p_type => $ctype ) {
			WP_CLI::line( $p2p_type );
		}
	}

	function create_connections( $args ) {
		$n = 10;

		if ( empty( $args ) ) {
			WP_CLI::line( "usage: wp p2p " . __FUNCTION__ . " <connection-type>" );
			exit;
		}

		list( $connection_type ) = $args;

		$ctype = p2p_type( $connection_type );
		if ( !$ctype )
			WP_CLI::error( "'$connection_type' is not a registered connection type." );

		$directed = $ctype->set_direction( 'from' );

		$side = $directed->get( 'current', 'side' );

		$extra_qv = array( 'p2p:per_page' => $n );

		$candidate = $directed->get_connectable( 'any', $extra_qv, 'abstract' );

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

		WP_CLI::success( "Created $count connections." );
	}

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

