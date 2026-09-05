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
use WPCF7_ContactForm;
use WPCF7_Submission;
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

	/** After init(), the wpcf7_submission_result filter is registered. */
	public function test_init_registers_submission_result_filter(): void {
		Integration::instance()->init();

		$priority = has_filter( 'wpcf7_submission_result', [ Integration::instance(), 'reconcile_aborted_status' ] );

		$this->assertNotFalse( $priority, 'wpcf7_submission_result filter must be registered after init().' );
	}

	// -----------------------------------------------------------------------
	// send_data_to_api() / reconcile_aborted_status() — WEB-1180
	// -----------------------------------------------------------------------

	/** Tear down the test fixture. */
	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		delete_option( 'paubox_api_key' );
		delete_option( 'paubox_api_user' );
		parent::tear_down();
	}

	/**
	 * Builds a Paubox-enabled form post plus a submission mock wired for
	 * send_data_to_api(), sharing the plumbing across the tests below.
	 *
	 * @return array{0: WPCF7_ContactForm, 1: WPCF7_Submission} [$form, $submission].
	 */
	private function make_paubox_enabled_form_and_submission(): array {
		update_option( 'paubox_api_key', 'test-key' );
		update_option( 'paubox_api_user', 'test-user' );

		$form_id = self::factory()->post->create( [ 'post_type' => 'wpcf7_contact_form' ] );
		update_post_meta( $form_id, '_wpcf7_api_data', [ 'send_to_paubox' => 'on' ] );
		update_post_meta( $form_id, '_paubox_mail_to', 'to@test.com' );

		$form = $this->createMock( WPCF7_ContactForm::class );
		$form->method( 'id' )->willReturn( $form_id );

		$contact_form = $this->createMock( WPCF7_ContactForm::class );
		$contact_form->method( 'message' )->with( 'mail_sent_ok' )->willReturn( 'Thank you for your message.' );

		$submission = $this->createMock( WPCF7_Submission::class );
		$submission->method( 'get_posted_data' )->willReturn( [] );
		$submission->method( 'uploaded_files' )->willReturn( [] );
		$submission->method( 'get_contact_form' )->willReturn( $contact_form );

		return [ $form, $submission ];
	}

	/** A successful Paubox delivery sets $abort = true. */
	public function test_send_data_to_api_aborts_cf7_mailer_when_paubox_delivers(): void {
		add_filter(
			'pre_http_request',
			static fn() => [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => '{"sourceTrackingId":"abc"}',
				'headers'  => [],
				'cookies'  => [],
				'filename' => '',
			]
		);

		[ $form, $submission ] = $this->make_paubox_enabled_form_and_submission();

		$abort = false;
		Integration::instance()->send_data_to_api( $form, $abort, $submission );

		$this->assertTrue( $abort, 'send_data_to_api() must abort CF7\'s own mailer once Paubox delivers.' );
	}

	/**
	 * Reconcile_aborted_status() rewrites "aborted" to "mail_sent" when the
	 * abort was caused by a confirmed Paubox delivery (the WEB-1180 fix).
	 */
	public function test_reconcile_aborted_status_rewrites_to_mail_sent_when_paubox_delivered(): void {
		add_filter(
			'pre_http_request',
			static fn() => [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => '{"sourceTrackingId":"abc"}',
				'headers'  => [],
				'cookies'  => [],
				'filename' => '',
			]
		);

		[ $form, $submission ] = $this->make_paubox_enabled_form_and_submission();

		$abort = false;
		Integration::instance()->send_data_to_api( $form, $abort, $submission );

		$result = Integration::instance()->reconcile_aborted_status(
			[
				'status'  => 'aborted',
				'message' => 'Sending mail has been aborted.',
			],
			$submission
		);

		$this->assertSame( 'mail_sent', $result['status'] );
		$this->assertSame( 'Thank you for your message.', $result['message'] );
	}

	/** Reconcile_aborted_status() leaves "aborted" untouched when Paubox did not deliver. */
	public function test_reconcile_aborted_status_leaves_aborted_when_paubox_did_not_deliver(): void {
		add_filter( 'pre_http_request', static fn() => new \WP_Error( 'http_request_failed', 'Could not resolve host.' ) );

		[ $form, $submission ] = $this->make_paubox_enabled_form_and_submission();

		$abort = false;
		Integration::instance()->send_data_to_api( $form, $abort, $submission );

		$this->assertFalse( $abort, 'send_data_to_api() must not abort CF7\'s own mailer when Paubox delivery fails.' );

		$result = Integration::instance()->reconcile_aborted_status(
			[
				'status'  => 'aborted',
				'message' => 'Sending mail has been aborted.',
			],
			$submission
		);

		$this->assertSame( 'aborted', $result['status'], 'A non-Paubox abort must not be rewritten to mail_sent.' );
	}

	/** Reconcile_aborted_status() leaves non-"aborted" statuses untouched, even after a successful delivery. */
	public function test_reconcile_aborted_status_leaves_other_statuses_untouched(): void {
		add_filter(
			'pre_http_request',
			static fn() => [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => '{"sourceTrackingId":"abc"}',
				'headers'  => [],
				'cookies'  => [],
				'filename' => '',
			]
		);

		[ $form, $submission ] = $this->make_paubox_enabled_form_and_submission();

		$abort = false;
		Integration::instance()->send_data_to_api( $form, $abort, $submission );

		$result = Integration::instance()->reconcile_aborted_status(
			[
				'status'  => 'validation_failed',
				'message' => 'One or more fields have an error.',
			],
			$submission
		);

		$this->assertSame( 'validation_failed', $result['status'] );
	}
}
