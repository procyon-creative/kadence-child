<?php

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $tests_dir ) {
    throw new RuntimeException( 'Run integration tests with npm test.' );
}

// Read only the generated configuration before allowing WordPress to touch a database.
require_once $tests_dir . '/wp-tests-config.php';
if ( ! defined( 'KADENCE_CHILD_TEST_ENVIRONMENT' ) || true !== KADENCE_CHILD_TEST_ENVIRONMENT ) {
    throw new RuntimeException( 'Tests require the isolated .wp-env.test.json environment. Run npm test.' );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
require_once $tests_dir . '/includes/functions.php';

// Load the child and parent themes through WordPress's normal theme bootstrap.
$GLOBALS['wp_tests_options'] = array(
    'template'   => 'kadence',
    'stylesheet' => 'kadence-child',
);

// Integration tests must not contact GitHub or other external services.
tests_add_filter( 'pre_http_request', function () {
    return new WP_Error( 'http_disabled_in_tests', 'External HTTP requests are disabled in tests.' );
} );

require_once $tests_dir . '/includes/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
