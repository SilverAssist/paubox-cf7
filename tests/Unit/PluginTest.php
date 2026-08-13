<?php
/**
 * Paubox CF7 Integration - Plugin Smoke Test
 *
 * Verifies that the plugin constants and autoloader are loaded.
 *
 * @package SilverAssist\PauboxCF7\Tests\Unit
 * @since   1.0.0
 * @version 1.0.0
 */

namespace SilverAssist\PauboxCF7\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for the plugin bootstrap.
 *
 * @since 1.0.0
 */
class PluginTest extends TestCase {

	/**
	 * Verifies that the autoloader resolves the Integration class.
	 */
	public function testIntegrationClassExists(): void {
		$this->assertTrue(
			\class_exists( \SilverAssist\PauboxCF7\CF7\Integration::class, true ),
			'CF7\Integration class must be autoloadable.'
		);
	}

	/**
	 * Verifies that the autoloader resolves the ApiClient class.
	 */
	public function testApiClientClassExists(): void {
		$this->assertTrue(
			\class_exists( \SilverAssist\PauboxCF7\Service\ApiClient::class, true ),
			'Service\ApiClient class must be autoloadable.'
		);
	}

	/**
	 * Verifies that the autoloader resolves the SettingsPage class.
	 */
	public function testSettingsPageClassExists(): void {
		$this->assertTrue(
			\class_exists( \SilverAssist\PauboxCF7\Admin\SettingsPage::class, true ),
			'Admin\SettingsPage class must be autoloadable.'
		);
	}
}
