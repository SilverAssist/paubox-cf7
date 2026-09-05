<?php
/**
 * Paubox CF7 Integration - Activator Schema Tests
 *
 * WP_UnitTestCase wraps every test in a transaction and — specifically to
 * keep custom-table DDL from leaking into the shared test database —
 * silently rewrites any CREATE TABLE/DROP TABLE query into CREATE/DROP
 * TEMPORARY TABLE for the test's duration (see WP_UnitTestCase_Base's
 * _create_temporary_tables()/_drop_temporary_tables() query filters). That's
 * the right behavior for tests that only need the delivery log table to
 * exist (DeliveryLogTest, SettingsPageTest, CF7\IntegrationTest), but it
 * makes it impossible to verify create_table()/drop_table() themselves
 * from inside that wrapper: any CREATE/DROP TABLE issued there only ever
 * touches a session-scoped temporary table, never the real one production
 * code creates or drops.
 *
 * These tests extend the plain PHPUnit TestCase instead of WP_UnitTestCase,
 * so DDL runs for real, unfiltered — same as it does for an actual site.
 * WordPress itself is still fully bootstrapped (tests/bootstrap.php runs
 * once for the whole process), so $wpdb, dbDelta(), and the options API
 * all work normally; only the per-test transaction wrapper is skipped, so
 * cleanup here is manual instead of automatic.
 *
 * @package SilverAssist\PauboxCF7\Tests\Integration\Core
 * @since   1.1.0
 * @version 1.1.0
 */

namespace SilverAssist\PauboxCF7\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use SilverAssist\PauboxCF7\Core\Activator;
use SilverAssist\PauboxCF7\Service\DeliveryLog;

/**
 * Integration tests for Activator's real (non-temporary) table DDL.
 *
 * @covers \SilverAssist\PauboxCF7\Core\Activator
 * @covers \SilverAssist\PauboxCF7\Service\DeliveryLog
 * @since 1.1.0
 */
class ActivatorSchemaTest extends TestCase {

	/**
	 * Restores the table and version option so later, WP_UnitTestCase-based
	 * tests (which assume both already exist) aren't affected by these.
	 */
	protected function tearDown(): void {
		delete_option( 'paubox_cf7_db_version' );
		DeliveryLog::create_table();
		parent::tearDown();
	}

	/** Activate() creates the real delivery log table. */
	public function test_activate_creates_the_real_table(): void {
		DeliveryLog::drop_table();

		Activator::activate();

		global $wpdb;
		$table  = DeliveryLog::table_name();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$this->assertSame( $table, $exists );
	}

	/** Uninstall() drops the real delivery log table. */
	public function test_uninstall_drops_the_real_table(): void {
		Activator::activate();

		Activator::uninstall();

		global $wpdb;
		$table  = DeliveryLog::table_name();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$this->assertNull( $exists );
	}

	/** Maybe_upgrade() recreates the real table when the stored version is stale. */
	public function test_maybe_upgrade_recreates_the_real_table_when_version_is_stale(): void {
		DeliveryLog::drop_table();
		update_option( 'paubox_cf7_db_version', '0.0.1' );

		Activator::maybe_upgrade();

		global $wpdb;
		$table  = DeliveryLog::table_name();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$this->assertSame( $table, $exists );
		$this->assertNotSame( '0.0.1', get_option( 'paubox_cf7_db_version' ) );
	}
}
