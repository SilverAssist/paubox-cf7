<?php
/**
 * Paubox CF7 Integration — HTTP API Client
 *
 * Handles all communication with the Paubox REST API: builds the
 * authenticated request, processes submission data and attachments,
 * and returns the raw WP HTTP response or a WP_Error on failure.
 *
 * @package SilverAssist\PauboxCF7\Service
 * @since   1.0.0
 * @version 1.0.0
 */

namespace SilverAssist\PauboxCF7\Service;

use stdClass;
use WPCF7_Submission;
use WP_Error;

\defined( 'ABSPATH' ) || exit;

/**
 * Paubox HTTP API client.
 *
 * Reads credentials from WordPress options and exposes a single public
 * method for sending an encrypted email through the Paubox REST API.
 *
 * @since 1.0.0
 */
class ApiClient {

	/**
	 * Sends a message via the Paubox API.
	 *
	 * @param string $from        Sender email address.
	 * @param array  $recipients  Array of recipient email addresses.
	 * @param string $subject     Email subject line.
	 * @param string $body        Plain-text email body.
	 * @param array  $attachments Array of stdClass attachment objects.
	 * @return array|\WP_Error API response array on success, WP_Error on failure.
	 */
	public function send_mail(
		string $from,
		array $recipients,
		string $subject,
		string $body,
		array $attachments
	): array|\WP_Error {
		$api_key  = get_option( 'paubox_api_key' );
		$api_user = get_option( 'paubox_api_user' );

		if ( empty( $api_key ) || empty( $api_user ) ) {
			wp_trigger_error( __METHOD__, 'Paubox API key or user is not set.', E_USER_WARNING );
			return new WP_Error( 'paubox_api_error', 'Paubox API key or user is not set.' );
		}

		// Filter out blank and malformed addresses before further processing.
		$recipients = \array_values( \array_filter( \array_map( 'sanitize_email', $recipients ) ) );

		if ( empty( $recipients ) ) {
			wp_trigger_error( __METHOD__, 'Paubox API Error: No valid recipients provided.', E_USER_WARNING );
			return new WP_Error( 'paubox_api_error', 'No valid recipients provided.' );
		}

		$endpoint = sprintf( 'https://api.paubox.net/v1/%s/messages', rawurlencode( $api_user ) );

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Token token=' . $api_key,
			],
			'body'    => wp_json_encode(
				[
					'data' => [
						'message' => [
							'headers'     => [
								'subject' => $subject,
								'from'    => sanitize_email( $from ),
							],
							'recipients'  => \array_map( 'sanitize_email', $recipients ),
							'content'     => [
								'text/plain' => $body,
							],
							'attachments' => $attachments,
						],
					],
				]
			),
		];

		$response = wp_remote_post( $endpoint, $args );

		do_action( 'paubox_cf7_after_send_lead', $response, $body );

		// Return a WP_Error for transport failures and non-2xx HTTP responses.
		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $http_code < 200 || $http_code >= 300 ) {
			$body_text = wp_remote_retrieve_body( $response );
			wp_trigger_error( __METHOD__, "Paubox API returned HTTP {$http_code}", E_USER_WARNING );
			return new \WP_Error( 'paubox_api_http_error', "Paubox API returned HTTP {$http_code}", [ 'status' => $http_code ] );
		}

		return $response;
	}

	/**
	 * Builds the plain-text email body from a submission and template.
	 *
	 * Replaces all `[field-name]` placeholders in the template with the
	 * corresponding submitted values, then strips any unmatched placeholders.
	 *
	 * @param WPCF7_Submission $submission The CF7 submission object.
	 * @param array            $data       Field-to-API-key mapping array.
	 * @param string           $template   Email body template with tag placeholders.
	 * @return string Processed email body with all tags replaced.
	 */
	public function get_email_body( WPCF7_Submission $submission, array $data, string $template = '' ): string {
		$posted = $submission->get_posted_data();

		foreach ( $data as $form_key => $api_key ) {
			if ( \is_array( $api_key ) ) {
				$values = [];
				foreach ( $posted[ $form_key ] ?? [] as $value ) {
					if ( $value ) {
						$value    = apply_filters( 'paubox_cf7_record_value', $value, $api_key );
						$values[] = $value;
					}
				}
				$template = \str_replace( "[{$form_key}]", \implode( ', ', $values ), $template );
			} else {
				$value = $posted[ $form_key ] ?? '';
				if ( \is_array( $value ) ) {
					$value = \reset( $value );
				}
				$template = \str_replace( "[{$form_key}]", $value, $template );
			}
		}

		// Strip sub-field placeholders that were not replaced.
		foreach ( $data as $form_key => $api_key ) {
			if ( \is_array( $api_key ) ) {
				foreach ( $api_key as $field_suffix => $name ) {
					$template = \str_replace( "[{$form_key}-{$field_suffix}]", '', $template );
				}
			}
		}

		// Strip any remaining unmatched [tag] placeholders.
		$template = (string) preg_replace( '/\[[^\]]+\]/', '', $template );

		$record = apply_filters( 'paubox_cf7_create_record', $template, $posted, $data, $template );

		return (string) $record;
	}

	/**
	 * Collects uploaded-file attachments referenced in the email template.
	 *
	 * Only files whose tag name appears in `$template` are included. Each file
	 * is base64-encoded and wrapped in a stdClass for the Paubox API payload.
	 *
	 * @param WPCF7_Submission $submission The CF7 submission object.
	 * @param string           $template   Email template used to match attachment tags.
	 * @return array Array of stdClass attachment objects ready for the API.
	 */
	public function get_email_attachments( WPCF7_Submission $submission, string $template = '' ): array {
		$uploaded_files = $submission->uploaded_files();
		$attachments    = [];

		foreach ( (array) $uploaded_files as $name => $paths ) {
			if ( false === \strpos( $template, "[{$name}]" ) ) {
				continue;
			}
			foreach ( $paths as $file ) {
				if ( ! empty( $file ) && \file_exists( $file ) ) {
					$info                     = \pathinfo( $file );
					$attachment               = new stdClass();
					$attachment->file_name    = $info['basename']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Paubox API field names.
					$attachment->content_type = \mime_content_type( $file ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Paubox API field names.
					$attachment->content      = \base64_encode( \file_get_contents( $file ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					$attachments[]            = $attachment;
				}
			}
		}

		return $attachments;
	}

	/**
	 * Resolves the recipient email address(es) from the CF7 submission.
	 *
	 * Reads the value of the form field whose tag name is stored in
	 * `$recipient_tag`, splits comma-separated lists, and filters out
	 * invalid or duplicate addresses.
	 *
	 * @param WPCF7_Submission $submission    The CF7 submission object.
	 * @param string           $recipient_tag Form field tag that holds the recipient email.
	 * @return array Array of validated recipient email addresses.
	 */
	public function get_recipient_email( WPCF7_Submission $submission, string $recipient_tag = '' ): array {
		if ( empty( $recipient_tag ) ) {
			return [];
		}

		$data = $submission->get_posted_data();
		if ( empty( $data ) ) {
			return [];
		}

		$tag = \str_replace( [ '[', ']' ], '', \trim( $recipient_tag ) );

		$raw = \trim( $data[ $tag ] ?? '' );
		if ( empty( $raw ) ) {
			return [];
		}

		$emails = \strpos( $raw, ',' ) !== false
			? \explode( ',', $raw )
			: [ $raw ];

		$emails = \array_map( 'trim', $emails );
		$emails = \array_filter( $emails );
		$emails = \array_unique( $emails );
		$emails = \array_values(
			\array_filter(
				$emails,
				static fn( string $e ): bool => (bool) \is_email( $e )
			)
		);

		return $emails;
	}

	/**
	 * Returns the mail tags available on the given contact form.
	 *
	 * @param \WPCF7_ContactForm $post The CF7 contact form object.
	 * @param array              $args Optional include/exclude filter arguments.
	 * @return \WPCF7_FormTag[] Array of form tag objects.
	 */
	public function get_mail_tags( \WPCF7_ContactForm $post, array $args ): array {
		$tags     = apply_filters( 'paubox_cf7_collect_mail_tags', $post->scan_form_tags() ); /** @var \WPCF7_FormTag[] $tags */
		$mailtags = [];

		foreach ( (array) $tags as $tag ) {
			$type = \trim( $tag['type'], '*' );
			if ( empty( $type ) || empty( $tag['name'] ) ) {
				continue;
			}
			if ( ! empty( $args['include'] ) && ! \in_array( $type, $args['include'], true ) ) {
				continue;
			}
			if ( ! empty( $args['exclude'] ) && \in_array( $type, $args['exclude'], true ) ) {
				continue;
			}
			$mailtags[] = $tag;
		}

		return $mailtags;
	}
}
