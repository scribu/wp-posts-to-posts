<?php

require_once dirname( __FILE__ ) . '/util.php';
require_once dirname( __FILE__ ) . '/api.php';
require_once dirname( __FILE__ ) . '/autoload.php';

P2P_Autoload::register( 'P2P_', dirname( __FILE__ ) );

P2P_Storage::init();

P2P_Query_Post::init();
P2P_Query_User::init();

P2P_URL_Query::init();

P2P_Widget::init();
P2P_Shortcodes::init();

