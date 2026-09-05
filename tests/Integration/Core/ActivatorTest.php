<?php
/**
 * Paubox CF7 Integration - Activator Integration Tests
 *
 * @package SilverAssist\PauboxCF7\Tests\Integration\Core
 * @since   1.0.1
 * @version 1.1.0
 */

namespace SilverAssist\PauboxCF7\Tests\Integration\Core;

use SilverAssist\PauboxCF7\Admin\SettingsPage;
use SilverAssist\PauboxCF7\Core\Activator;
use WP_UnitTestCase;

/**
 * Integration tests for Activator's option-level behavior.
 *
 * The delivery-log-table assertions ("activate() really creates it",
 * "uninstall() really drops it") live in ActivatorSchemaTest instead —
 * WP_UnitTestCase silently rewrites CREATE/DROP TABLE into CREATE/DROP
 * TEMPORARY TABLE for the duration of every test, so DDL run from here
 * would only ever touch a phantom temporary table, never the real one.
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
		delete_option( 'paubox_cf7_db_version' );
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

	/** Uninstall() removes the stored db version option. */
	public function test_uninstall_removes_db_version_option(): void {
		update_option( 'paubox_cf7_db_version', '1.1.0' );

		Activator::uninstall();

		$this->assertFalse( get_option( 'paubox_cf7_db_version' ) );
	}

	// -----------------------------------------------------------------------
	// activate() / maybe_upgrade() — option bookkeeping
	// -----------------------------------------------------------------------

	/** Activate() records the current schema version. */
	public function test_activate_records_db_version(): void {
		delete_option( 'paubox_cf7_db_version' );

		Activator::activate();

		$this->assertNotFalse( get_option( 'paubox_cf7_db_version' ) );
	}

	/** Maybe_upgrade() is a no-op once the stored version already matches. */
	public function test_maybe_upgrade_is_noop_when_version_matches(): void {
		Activator::activate();
		$before = get_option( 'paubox_cf7_db_version' );

		Activator::maybe_upgrade();

		$this->assertSame( $before, get_option( 'paubox_cf7_db_version' ) );
	}

	/** Maybe_upgrade() updates the stored version when it's stale. */
	public function test_maybe_upgrade_updates_stale_version(): void {
		update_option( 'paubox_cf7_db_version', '0.0.1' );

		Activator::maybe_upgrade();

		$this->assertNotSame( '0.0.1', get_option( 'paubox_cf7_db_version' ) );
	}
}
