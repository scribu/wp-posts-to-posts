<?php

require_once dirname( __FILE__ ) . '/lib/functions.php';

tests_add_filter( 'muplugins_loaded', function() {
	require dirname( __FILE__ ) . '/../posts-to-posts.php';
	require dirname( __FILE__ ) . '/../debug-utils.php';
} );

require dirname( __FILE__ ) . '/lib/bootstrap.php';

