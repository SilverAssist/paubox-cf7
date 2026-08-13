<?php
/**
 * Paubox CF7 Integration - Activator Integration Tests
 *
 * @package SilverAssist\PauboxCF7\Tests\Integration\Core
 * @since   1.0.1
 * @version 1.0.1
 */

namespace SilverAssist\PauboxCF7\Tests\Integration\Core;

use SilverAssist\PauboxCF7\Admin\SettingsPage;
use SilverAssist\PauboxCF7\Core\Activator;
use WP_UnitTestCase;

/**
 * Integration tests for Activator.
 *
 * @covers \SilverAssist\PauboxCF7\Core\Activator
 * @since 1.0.1
 */
class ActivatorTest extends WP_UnitTestCase {

	/**
	 * Cleans up options this test class may have set.
	 */
	public function tear_down(): void {
		foreach ( SettingsPage::OPTIONS as $option ) {
			delete_option( $option );
		}
		delete_option( 'paubox_cf7_unrelated_option' );
		parent::tear_down();
	}

	/** Uninstall() removes only the plugin's own options. */
	public function test_uninstall_removes_plugin_options_only(): void {
		foreach ( SettingsPage::OPTIONS as $option ) {
			update_option( $option, 'test-value' );
		}
		update_option( 'paubox_cf7_unrelated_option', 'should-survive' );

		Activator::uninstall();

		foreach ( SettingsPage::OPTIONS as $option ) {
			$this->assertFalse( get_option( $option ), "{$option} should be removed after uninstall." );
		}
		$this->assertSame( 'should-survive', get_option( 'paubox_cf7_unrelated_option' ) );
	}
}
