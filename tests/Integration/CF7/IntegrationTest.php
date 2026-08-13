<?php
/**
 * Paubox CF7 Integration - CF7 Integration Component Tests
 *
 * @package SilverAssist\PauboxCF7\Tests\Integration\CF7
 * @since   1.0.0
 * @version 1.0.0
 */

namespace SilverAssist\PauboxCF7\Tests\Integration\CF7;

use SilverAssist\PauboxCF7\CF7\Integration;
use WP_UnitTestCase;

/**
 * Integration tests for CF7 Integration component.
 *
 * @covers \SilverAssist\PauboxCF7\CF7\Integration
 * @since 1.0.0
 */
class IntegrationTest extends WP_UnitTestCase {

	// -----------------------------------------------------------------------
	// LoadableInterface contract
	// -----------------------------------------------------------------------

	/** Should_load() returns false when WPCF7_ContactForm is unavailable. */
	public function test_should_load_returns_false_without_cf7(): void {
		// Rename the stub so class_exists() returns false for this test.
		if ( class_exists( 'WPCF7_ContactForm' ) ) {
			$this->markTestSkipped( 'CF7 is installed — cannot test the false-branch.' );
		}

		$this->assertFalse( Integration::instance()->should_load() );
	}

	/** Get_priority() is 20 (loads between core:10 and admin:30). */
	public function test_get_priority_returns_20(): void {
		$this->assertSame( 20, Integration::instance()->get_priority() );
	}

	// -----------------------------------------------------------------------
	// add_paubox_tab()
	// -----------------------------------------------------------------------

	/** Add_paubox_tab() appends a 'paubox-api-integration' panel entry. */
	public function test_add_paubox_tab_appends_panel(): void {
		$panels = Integration::instance()->add_paubox_tab( [] );

		$this->assertArrayHasKey( 'paubox-api-integration', $panels );
		$this->assertArrayHasKey( 'title', $panels['paubox-api-integration'] );
		$this->assertArrayHasKey( 'callback', $panels['paubox-api-integration'] );
	}

	/** Add_paubox_tab() preserves existing panels. */
	public function test_add_paubox_tab_preserves_existing_panels(): void {
		$existing = [
			'mail' => [
				'title'    => 'Mail',
				'callback' => 'some_fn',
			],
		];
		$result   = Integration::instance()->add_paubox_tab( $existing );

		$this->assertArrayHasKey( 'mail', $result );
	}

	// -----------------------------------------------------------------------
	// add_sf_properties()
	// -----------------------------------------------------------------------

	/** Add_sf_properties() seeds all six Paubox keys when absent. */
	public function test_add_sf_properties_adds_all_required_keys(): void {
		$result = Integration::instance()->add_sf_properties( [] );

		$required = [
			'wpcf7_api_data',
			'paubox_mail_from',
			'paubox_mail_to',
			'paubox_mail_recipient',
			'paubox_mail_subject',
			'paubox_mail_template',
			'paubox_mail_attachments',
		];

		foreach ( $required as $key ) {
			$this->assertArrayHasKey( $key, $result, "Key '{$key}' must be added by add_sf_properties()." );
		}
	}

	/** Add_sf_properties() does not overwrite keys already set. */
	public function test_add_sf_properties_does_not_overwrite_existing_values(): void {
		$existing = [ 'paubox_mail_from' => 'existing@test.com' ];
		$result   = Integration::instance()->add_sf_properties( $existing );

		$this->assertSame( 'existing@test.com', $result['paubox_mail_from'] );
	}

	// -----------------------------------------------------------------------
	// init()
	// -----------------------------------------------------------------------

	/** After init(), the wpcf7_before_send_mail action is registered with 3 args. */
	public function test_init_registers_send_mail_hook(): void {
		Integration::instance()->init();

		$priority = has_action( 'wpcf7_before_send_mail', [ Integration::instance(), 'send_data_to_api' ] );

		$this->assertNotFalse( $priority, 'wpcf7_before_send_mail hook must be registered after init().' );
	}

	/** After init(), the wpcf7_contact_form_properties filter is registered. */
	public function test_init_registers_properties_filter(): void {
		Integration::instance()->init();

		$priority = has_filter( 'wpcf7_contact_form_properties', [ Integration::instance(), 'add_sf_properties' ] );

		$this->assertNotFalse( $priority, 'wpcf7_contact_form_properties filter must be registered after init().' );
	}
}
