<?php
/**
 * PHPUnit bootstrap for paubox-cf7.
 * Unit tests run without WordPress. Integration tests require WP_TESTS_DIR.
 */
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( 'integration' === ( $_ENV['TESTSUITE'] ?? '' ) ) {
    $wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';
    if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
        throw new RuntimeException( "WP test suite not found at {$wp_tests_dir}." );
    }
    require_once $wp_tests_dir . '/includes/bootstrap.php';
}
