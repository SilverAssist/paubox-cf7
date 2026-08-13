<?php
/**
 * Paubox CF7 Integration - ApiClient Integration Tests
 *
 * Tests send_mail(), get_email_body(), and get_recipient_email()
 * against a real WordPress environment.
 *
 * @package SilverAssist\PauboxCF7\Tests\Integration\Service
 * @since   1.0.0
 * @version 1.0.0
 */

namespace SilverAssist\PauboxCF7\Tests\Integration\Service;

use SilverAssist\PauboxCF7\Service\ApiClient;
use WP_UnitTestCase;

/**
 * Integration tests for ApiClient.
 *
 * @covers \SilverAssist\PauboxCF7\Service\ApiClient
 * @since 1.0.0
 */
class ApiClientTest extends WP_UnitTestCase {

	/**
	 * Paubox API client under test.
	 *
	 * @var ApiClient
	 */
	private ApiClient $client;

	/**
	 * Set up the test fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->client = new ApiClient();
	}

	/**
	 * Tear down the test fixture.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );
		delete_option( 'paubox_api_key' );
		delete_option( 'paubox_api_user' );
		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// send_mail()
	// -----------------------------------------------------------------------

	/** Missing credentials → WP_Error before any HTTP call. */
	public function test_send_mail_returns_wp_error_when_credentials_missing(): void {
		// @phpcs:ignore WordPress.PHP.NoSilencedErrors -- we expect the wp_trigger_error warning.
		$result = @$this->client->send_mail( 'from@test.com', [ 'to@test.com' ], 'Subject', 'Body', [] );

		$this->assertWPError( $result );
		$this->assertSame( 'paubox_api_error', $result->get_error_code() );
	}

	/** Blank and malformed recipient addresses are filtered → WP_Error. */
	public function test_send_mail_returns_wp_error_for_blank_recipients(): void {
		update_option( 'paubox_api_key', 'test-key' );
		update_option( 'paubox_api_user', 'test-user' );

		// @phpcs:ignore WordPress.PHP.NoSilencedErrors -- we expect the wp_trigger_error warning.
		$result = @$this->client->send_mail( 'from@test.com', [ '', '  ', 'not-an-email' ], 'Subject', 'Body', [] );

		$this->assertWPError( $result );
		$this->assertSame( 'paubox_api_error', $result->get_error_code() );
	}

	/** HTTP 4xx response → WP_Error (Bug #2 fix). */
	public function test_send_mail_returns_wp_error_for_http_4xx(): void {
		update_option( 'paubox_api_key', 'test-key' );
		update_option( 'paubox_api_user', 'test-user' );

		add_filter(
			'pre_http_request',
			static fn() => [
				'response' => [
					'code'    => 401,
					'message' => 'Unauthorized',
				],
				'body'     => 'Unauthorized',
				'headers'  => [],
				'cookies'  => [],
				'filename' => '',
			]
		);

		// @phpcs:ignore WordPress.PHP.NoSilencedErrors -- we expect the wp_trigger_error warning.
		$result = @$this->client->send_mail( 'from@test.com', [ 'to@test.com' ], 'Subject', 'Body', [] );

		$this->assertWPError( $result );
		$this->assertSame( 'paubox_api_http_error', $result->get_error_code() );
	}

	/** HTTP 500 response → WP_Error (Bug #2 fix). */
	public function test_send_mail_returns_wp_error_for_http_5xx(): void {
		update_option( 'paubox_api_key', 'test-key' );
		update_option( 'paubox_api_user', 'test-user' );

		add_filter(
			'pre_http_request',
			static fn() => [
				'response' => [
					'code'    => 500,
					'message' => 'Server Error',
				],
				'body'     => 'Error',
				'headers'  => [],
				'cookies'  => [],
				'filename' => '',
			]
		);

		// @phpcs:ignore WordPress.PHP.NoSilencedErrors -- we expect the wp_trigger_error warning.
		$result = @$this->client->send_mail( 'from@test.com', [ 'to@test.com' ], 'Subject', 'Body', [] );

		$this->assertWPError( $result );
	}

	/** HTTP 200 → returns the response array (not a WP_Error). */
	public function test_send_mail_returns_response_for_http_200(): void {
		update_option( 'paubox_api_key', 'test-key' );
		update_option( 'paubox_api_user', 'test-user' );

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

		$result = $this->client->send_mail( 'from@test.com', [ 'to@test.com' ], 'Subject', 'Body', [] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
	}

	// -----------------------------------------------------------------------
	// get_email_body()
	// -----------------------------------------------------------------------

	/** Known [field] placeholders are replaced with submitted values. */
	public function test_get_email_body_replaces_known_placeholders(): void {
		$submission = $this->createMock( \WPCF7_Submission::class );
		$submission->method( 'get_posted_data' )
			->willReturn(
				[
					'your-name'  => 'Alice',
					'your-email' => 'alice@test.com',
				]
			);

		$data     = [
			'your-name'  => 'your-name',
			'your-email' => 'your-email',
		];
		$template = 'Hello [your-name], reply to [your-email].';

		$result = $this->client->get_email_body( $submission, $data, $template );

		$this->assertSame( 'Hello Alice, reply to alice@test.com.', $result );
	}

	/** Unmatched [unknown-tag] placeholders are stripped from the output (Bug #3 fix). */
	public function test_get_email_body_strips_unmatched_placeholders(): void {
		$submission = $this->createMock( \WPCF7_Submission::class );
		$submission->method( 'get_posted_data' )->willReturn( [] );

		$result = $this->client->get_email_body( $submission, [], 'Hello [unknown-tag] world.' );

		$this->assertStringNotContainsString( '[unknown-tag]', $result );
		$this->assertSame( 'Hello  world.', $result );
	}

	// -----------------------------------------------------------------------
	// get_recipient_email()
	// -----------------------------------------------------------------------

	/** Empty recipient tag → empty array. */
	public function test_get_recipient_email_returns_empty_for_empty_tag(): void {
		$submission = $this->createMock( \WPCF7_Submission::class );
		$submission->method( 'get_posted_data' )->willReturn( [] );

		$this->assertSame( [], $this->client->get_recipient_email( $submission, '' ) );
	}

	/** Invalid email addresses are filtered out. */
	public function test_get_recipient_email_filters_invalid_emails(): void {
		$submission = $this->createMock( \WPCF7_Submission::class );
		$submission->method( 'get_posted_data' )->willReturn( [ 'email-field' => 'not-an-email' ] );

		$this->assertSame( [], $this->client->get_recipient_email( $submission, '[email-field]' ) );
	}

	/** Comma-separated list of valid emails is split and returned. */
	public function test_get_recipient_email_splits_comma_separated_list(): void {
		$submission = $this->createMock( \WPCF7_Submission::class );
		$submission->method( 'get_posted_data' )->willReturn( [ 'emails' => 'a@test.com, b@test.com' ] );

		$result = $this->client->get_recipient_email( $submission, '[emails]' );

		$this->assertCount( 2, $result );
		$this->assertContains( 'a@test.com', $result );
		$this->assertContains( 'b@test.com', $result );
	}
}
