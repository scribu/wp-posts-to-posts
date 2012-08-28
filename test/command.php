<?php

WP_CLI::add_command( 'p2p', 'P2P_CLI_Command' );

class P2P_CLI_Command extends WP_CLI_Command {

	function create_connections( $args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( "usage: wp p2p " . __FUNCTION__ . " <connection-type>" );
			exit;
		}

		list( $connection_type ) = $args;

		$ctype = p2p_type( $connection_type );
		if ( !$ctype )
			WP_CLI::error( "'$connection_type' is not a registered connection type." );

		$generators = array(
			'post' => '_p2p_generate_post',
			'user' => '_p2p_generate_user'
		);

		$n = 10;

		$candidates = array();

		foreach ( array( 'from', 'to' ) as $end ) {
			if ( 'post' == $ctype->object[ $end ] ) {
				$candidates[ $end ] = _p2p_generate_posts( $ctype->side[ $end ]->first_post_type(), $n );
			} else {
				$candidates[ $end ] = _p2p_generate_users( $n );
			}
		}

		$count = 0;

		foreach ( $candidates['from'] as $i => $from ) {
			$start = $i % 3;

			$m = rand( 0, $n ) - $start;

			for ( $j = 0; $j < $m; $j++ ) {
				$ctype->connect( $from, $candidates['to'][ $j + $start ] );
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

