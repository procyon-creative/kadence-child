<?php

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'LOGGED_IN_KEY', 'unit-test-key' );
define( 'LOGGED_IN_SALT', 'unit-test-salt' );

// Load the settings functions without booting WordPress or registering real hooks.
Brain\Monkey\Functions\when( 'add_action' )->justReturn( true );
require_once dirname( __DIR__ ) . '/inc/admin.php';
Brain\Monkey\tearDown();
