<?php
/**
 * Paubox CF7 Integration - PHPUnit Bootstrap
 *
 * Unit tests load the autoloader only. Integration tests also bootstrap
 * the WordPress test suite from WP_TESTS_DIR.
 *
 * @package SilverAssist\PauboxCF7\Tests
 * @since   1.0.0
 * @version 1.0.0
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( 'integration' === ( getenv( 'TESTSUITE' ) ?: '' ) ) {
	$paubox_cf7_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	if ( ! file_exists( $paubox_cf7_tests_dir . '/includes/functions.php' ) ) {
		throw new \RuntimeException( 'WP test suite not found at ' . $paubox_cf7_tests_dir . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}
	require_once $paubox_cf7_tests_dir . '/includes/bootstrap.php';

	// Load CF7 stubs when CF7 is not installed in the test environment.
	if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
		require_once dirname( __DIR__ ) . '/vendor/miguelcolmenares/cf7-stubs/contact-form-7-stubs.php';
	}
}
