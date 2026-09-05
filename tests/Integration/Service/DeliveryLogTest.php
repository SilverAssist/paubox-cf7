<?php
/**
 * Paubox CF7 Integration - DeliveryLog Integration Tests
 *
 * @package SilverAssist\PauboxCF7\Tests\Integration\Service
 * @since   1.1.0
 * @version 1.1.0
 */

namespace SilverAssist\PauboxCF7\Tests\Integration\Service;

use SilverAssist\PauboxCF7\Service\DeliveryLog;
use WP_UnitTestCase;

/**
 * Integration tests for DeliveryLog.
 *
 * @covers \SilverAssist\PauboxCF7\Service\DeliveryLog
 * @since 1.1.0
 */
class DeliveryLogTest extends WP_UnitTestCase {

	/**
	 * Creates the table once, outside any per-test transaction — later
	 * dbDelta() calls against an unchanged schema issue no further DDL, so
	 * WP_UnitTestCase's per-test row rollback keeps working normally.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		DeliveryLog::create_table();
	}

	// -----------------------------------------------------------------------
	// table_name() / create_table()
	// -----------------------------------------------------------------------

	/** Table_name() is prefixed with $wpdb->prefix. */
	public function test_table_name_uses_wpdb_prefix(): void {
		global $wpdb;
		$this->assertSame( $wpdb->prefix . 'paubox_cf7_delivery_log', DeliveryLog::table_name() );
	}

	/** Create_table() creates a table that actually exists in the database. */
	public function test_create_table_creates_the_table(): void {
		global $wpdb;
		$table = DeliveryLog::table_name();

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$this->assertSame( $table, $exists );
	}

	// -----------------------------------------------------------------------
	// record() / get_recent()
	// -----------------------------------------------------------------------

	/** Record() persists a successful delivery, retrievable via get_recent(). */
	public function test_record_persists_a_successful_delivery(): void {
		DeliveryLog::record( 42, true, 200, '' );

		$rows = DeliveryLog::get_recent();

		$this->assertCount( 1, $rows );
		$this->assertSame( 42, (int) $rows[0]->form_id );
		$this->assertSame( 1, (int) $rows[0]->success );
		$this->assertSame( 200, (int) $rows[0]->http_code );
		$this->assertSame( '', $rows[0]->error_message );
	}

	/** Record() persists the error message on a failed delivery. */
	public function test_record_persists_failure_details(): void {
		DeliveryLog::record( 7, false, 0, 'Could not resolve host.' );

		$rows = DeliveryLog::get_recent();

		$this->assertSame( 0, (int) $rows[0]->success );
		$this->assertSame( 0, (int) $rows[0]->http_code );
		$this->assertSame( 'Could not resolve host.', $rows[0]->error_message );
	}

	/** Get_recent() returns rows newest first. */
	public function test_get_recent_orders_newest_first(): void {
		DeliveryLog::record( 1, true, 200, '' );
		DeliveryLog::record( 2, true, 200, '' );
		DeliveryLog::record( 3, false, 0, 'boom' );

		$rows     = DeliveryLog::get_recent();
		$form_ids = \array_map( static fn( $row ) => (int) $row->form_id, $rows );

		$this->assertSame( [ 3, 2, 1 ], $form_ids );
	}

	/** Get_recent() respects the $limit argument. */
	public function test_get_recent_respects_limit(): void {
		DeliveryLog::record( 1, true, 200, '' );
		DeliveryLog::record( 2, true, 200, '' );
		DeliveryLog::record( 3, true, 200, '' );

		$rows = DeliveryLog::get_recent( 2 );

		$this->assertCount( 2, $rows );
	}

	/** Get_recent() returns an empty array when there are no rows. */
	public function test_get_recent_returns_empty_array_when_no_rows(): void {
		$this->assertSame( [], DeliveryLog::get_recent() );
	}
}
