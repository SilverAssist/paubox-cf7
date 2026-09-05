<?php
/**
 * Paubox CF7 Integration - SettingsPage Integration Tests
 *
 * @package SilverAssist\PauboxCF7\Tests\Integration\Admin
 * @since   1.0.0
 * @version 1.0.0
 */

namespace SilverAssist\PauboxCF7\Tests\Integration\Admin;

use SilverAssist\PauboxCF7\Admin\SettingsPage;
use SilverAssist\PauboxCF7\Service\DeliveryLog;
use WP_UnitTestCase;

/**
 * @covers \SilverAssist\PauboxCF7\Admin\SettingsPage
 * @since 1.0.0
 */
class SettingsPageTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// register_settings() calls register_setting() which needs the admin context.
		set_current_screen( 'settings_page_paubox-cf7' );
	}

	public function tear_down(): void {
		unregister_setting( 'paubox_cf7_settings', 'paubox_api_key' );
		unregister_setting( 'paubox_cf7_settings', 'paubox_api_user' );
		parent::tear_down();
	}

	/** should_load() requires is_admin() and SettingsHub to exist. */
	public function test_should_load_returns_false_when_settings_hub_absent(): void {
		if ( class_exists( 'SilverAssist\SettingsHub\SettingsHub' ) ) {
			$this->markTestSkipped( 'SettingsHub is installed — cannot test the false-branch.' );
		}

		$this->assertFalse( SettingsPage::instance()->should_load() );
	}

	/** get_priority() is 30 (loads after Integration:20). */
	public function test_get_priority_returns_30(): void {
		$this->assertSame( 30, SettingsPage::instance()->get_priority() );
	}

	/** register_settings() registers paubox_api_key with sanitize_text_field. */
	public function test_register_settings_registers_api_key_option(): void {
		SettingsPage::instance()->register_settings();

		global $wp_registered_settings;
		$this->assertArrayHasKey( 'paubox_api_key', $wp_registered_settings );
	}

	/** register_settings() registers paubox_api_user with sanitize_text_field. */
	public function test_register_settings_registers_api_user_option(): void {
		SettingsPage::instance()->register_settings();

		global $wp_registered_settings;
		$this->assertArrayHasKey( 'paubox_api_user', $wp_registered_settings );
	}

	/** Both options belong to the paubox_cf7_settings group. */
	public function test_register_settings_uses_correct_group(): void {
		SettingsPage::instance()->register_settings();

		global $wp_registered_settings;
		$this->assertSame( 'paubox_cf7_settings', $wp_registered_settings['paubox_api_key']['group'] );
		$this->assertSame( 'paubox_cf7_settings', $wp_registered_settings['paubox_api_user']['group'] );
	}

	// -----------------------------------------------------------------------
	// render_settings_page() — Recent Deliveries section
	// -----------------------------------------------------------------------

	/** Render_settings_page() shows the empty-state message when there are no delivery log rows. */
	public function test_render_settings_page_shows_empty_state_when_no_deliveries(): void {
		\ob_start();
		SettingsPage::instance()->render_settings_page();
		$output = \ob_get_clean();

		$this->assertStringContainsString( 'No Paubox delivery attempts recorded yet.', $output );
	}

	/** Render_settings_page() lists a recorded delivery, including its error message. */
	public function test_render_settings_page_lists_a_recorded_delivery(): void {
		DeliveryLog::record( 123, false, 500, 'Server Error' );

		\ob_start();
		SettingsPage::instance()->render_settings_page();
		$output = \ob_get_clean();

		$this->assertStringContainsString( '#123', $output );
		$this->assertStringContainsString( 'Server Error', $output );
		$this->assertStringContainsString( 'Failed', $output );
	}
}
