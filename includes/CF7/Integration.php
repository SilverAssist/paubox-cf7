<?php
/**
 * Paubox CF7 Integration — CF7 Integration Component
 *
 * Registers Contact Form 7 hooks for the Paubox encrypted-email API:
 * intercepts form submissions, renders the editor settings panel,
 * and persists per-form configuration.
 *
 * @package SilverAssist\PauboxCF7\CF7
 * @since   1.0.0
 * @version 1.0.0
 */

namespace SilverAssist\PauboxCF7\CF7;

use SilverAssist\PauboxCF7\Service\ApiClient;
use SilverAssist\PluginKernel\Interfaces\LoadableInterface;
use WPCF7_ContactForm;
use WPCF7_Submission;

\defined( 'ABSPATH' ) || exit;

/**
 * Connects Contact Form 7 with the Paubox API.
 *
 * Registers the CF7 hooks needed to intercept form submissions and route
 * emails through the Paubox encrypted-delivery API.
 *
 * @since 1.0.0
 */
class Integration implements LoadableInterface {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return static
	 */
	public static function instance(): static {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * API client used to deliver messages.
	 *
	 * @var ApiClient
	 */
	private ApiClient $api_client;

	/**
	 * Private constructor — use instance() instead.
	 */
	private function __construct() {
		$this->api_client = new ApiClient();
	}

	/**
	 * Returns false when Contact Form 7 is not active.
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		return \class_exists( 'WPCF7_ContactForm' );
	}

	/**
	 * Returns 20 — loads after core services, before admin components.
	 *
	 * @return int
	 */
	public function get_priority(): int {
		return 20;
	}

	/**
	 * Registers all CF7 action and filter hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// 3 args: $form, &$abort, $submission — accept abort flag to suppress CF7's own mailer.
		add_action( 'wpcf7_before_send_mail', [ $this, 'send_data_to_api' ], 10, 3 );
		add_action( 'wpcf7_save_contact_form', [ $this, 'save_contact_form_details' ], 10, 1 );
		add_filter( 'wpcf7_editor_panels', [ $this, 'add_paubox_tab' ], 1, 1 );
		add_filter( 'wpcf7_contact_form_properties', [ $this, 'add_sf_properties' ], 10, 1 );
	}

	/**
	 * Adds a Paubox Integration tab to the CF7 form editor.
	 *
	 * @param array $panels Existing editor panel definitions.
	 * @return array Modified panels array with the Paubox tab appended.
	 */
	public function add_paubox_tab( array $panels ): array {
		$panels['paubox-api-integration'] = [
			'title'    => __( 'Paubox Integration', 'paubox-cf7' ),
			'callback' => [ $this, 'integrations' ],
		];

		return $panels;
	}

	/**
	 * Renders the Paubox settings panel inside the CF7 editor.
	 *
	 * @param WPCF7_ContactForm $post The contact form being edited.
	 * @return void
	 */
	public function integrations( WPCF7_ContactForm $post ): void {
		$wpcf7   = WPCF7_ContactForm::get_current();
		$form_id = $wpcf7->id();

		$api_data    = get_post_meta( $form_id, '_wpcf7_api_data', true );
		$mail_from   = get_post_meta( $form_id, '_paubox_mail_from', true );
		$mail_to     = get_post_meta( $form_id, '_paubox_mail_to', true );
		$recipient   = get_post_meta( $form_id, '_paubox_mail_recipient', true );
		$subject     = get_post_meta( $form_id, '_paubox_mail_subject', true );
		$template    = get_post_meta( $form_id, '_paubox_mail_template', true );
		$attachments = get_post_meta( $form_id, '_paubox_mail_attachments', true );

		$mail_tags = $this->api_client->get_mail_tags( $post, [] );

		if ( ! \is_array( $api_data ) ) {
			$api_data = [];
		}
		$api_data['send_to_paubox'] ??= 'off';
		?>
		<h2><?php echo esc_html__( 'Paubox Integration', 'paubox-cf7' ); ?></h2>

		<fieldset>
			<?php do_action( 'paubox_cf7_before_settings_fields', $post ); // phpcs:ignore WordPress.NamingConventions.ValidHookName ?>

			<div class="cf7_row">
				<label for="wpcf7-sf-send_to_paubox">
					<input type="checkbox"
						id="wpcf7-sf-send_to_paubox"
						name="wpcf7-sf[send_to_paubox]"
						<?php checked( $api_data['send_to_paubox'], 'on' ); ?> />
					<?php esc_html_e( 'Send mail with Paubox?', 'paubox-cf7' ); ?>
				</label>
			</div>

			<?php do_action( 'paubox_cf7_after_settings_fields', $post ); // phpcs:ignore WordPress.NamingConventions.ValidHookName ?>
		</fieldset>

		<fieldset>
			<div class="cf7_row">
				<h2><?php echo esc_html__( 'Paubox Mail', 'paubox-cf7' ); ?></h2>

				<legend>
					<?php esc_html_e( 'In the following fields, you can use these mail-tags:', 'paubox-cf7' ); ?>
					<br /><br />
					<?php foreach ( $mail_tags as $mail_tag ) : ?>
						<span class="xml_mailtag mailtag code">[<?php echo esc_html( $mail_tag->name ); ?>]</span>
					<?php endforeach; ?>
				</legend>

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="paubox_mail_from"><?php esc_html_e( 'From', 'paubox-cf7' ); ?></label>
							</th>
							<td>
								<input id="paubox_mail_from" name="paubox_mail_from"
									class="large-text code" size="70" dir="ltr"
									value="<?php echo esc_attr( (string) $mail_from ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="paubox_mail_recipient"><?php esc_html_e( 'Recipient email Tag', 'paubox-cf7' ); ?></label>
							</th>
							<td>
								<input id="paubox_mail_recipient" name="paubox_mail_recipient"
									class="large-text code" size="70" dir="ltr"
									value="<?php echo esc_attr( (string) $recipient ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="paubox_mail_to"><?php esc_html_e( 'Default recipient', 'paubox-cf7' ); ?></label>
							</th>
							<td>
								<input id="paubox_mail_to" name="paubox_mail_to"
									class="large-text code" size="70" dir="ltr"
									value="<?php echo esc_attr( (string) $mail_to ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="paubox_mail_subject"><?php esc_html_e( 'Subject', 'paubox-cf7' ); ?></label>
							</th>
							<td>
								<input id="paubox_mail_subject" name="paubox_mail_subject"
									class="large-text code" size="70" dir="ltr"
									value="<?php echo esc_attr( (string) $subject ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="paubox_mail_template"><?php esc_html_e( 'Message body', 'paubox-cf7' ); ?></label>
							</th>
							<td>
								<textarea id="paubox_mail_template" name="paubox_mail_template"
									class="large-text code" rows="12" dir="ltr"><?php echo esc_textarea( (string) $template ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="paubox_mail_attachments"><?php esc_html_e( 'File attachments', 'paubox-cf7' ); ?></label>
							</th>
							<td>
								<textarea id="paubox_mail_attachments" name="paubox_mail_attachments"
									class="large-text code" rows="4" dir="ltr"><?php echo esc_textarea( (string) $attachments ); ?></textarea>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Intercepts a CF7 submission and delivers it via the Paubox API.
	 *
	 * Sets `$abort = true` on successful delivery so CF7 skips its own mailer
	 * and does not send a duplicate email.
	 *
	 * @param WPCF7_ContactForm $form    The contact form being submitted.
	 * @param bool              &$abort  Set to true to prevent CF7's default mailer.
	 * @param WPCF7_Submission  $submission The current submission instance.
	 * @return void
	 */
	public function send_data_to_api( WPCF7_ContactForm $form, bool &$abort, WPCF7_Submission $submission ): void {
		$form_id  = $form->id();
		$api_data = get_post_meta( $form_id, '_wpcf7_api_data', true );

		if ( ! isset( $api_data['send_to_paubox'] ) || 'on' !== $api_data['send_to_paubox'] ) {
			return;
		}

		$mail_from       = (string) get_post_meta( $form_id, '_paubox_mail_from', true );
		$recipient       = (string) get_post_meta( $form_id, '_paubox_mail_recipient', true );
		$mail_to         = (string) get_post_meta( $form_id, '_paubox_mail_to', true );
		$subject_tpl     = (string) get_post_meta( $form_id, '_paubox_mail_subject', true );
		$template        = (string) get_post_meta( $form_id, '_paubox_mail_template', true );
		$attachments_tpl = (string) get_post_meta( $form_id, '_paubox_mail_attachments', true );

		// Build mapping from all submitted field names so every [tag] in templates resolves.
		$posted   = $submission->get_posted_data();
		$data_map = \array_combine( \array_keys( $posted ), \array_keys( $posted ) );

		$email_attachments = $this->api_client->get_email_attachments( $submission, $attachments_tpl );
		$email_body        = $this->api_client->get_email_body( $submission, $data_map, $template );
		$email_recipient   = $this->api_client->get_recipient_email( $submission, $recipient );
		$subject           = $this->api_client->get_email_body( $submission, $data_map, $subject_tpl );

		$recipients = empty( $email_recipient ) ? [ $mail_to ] : $email_recipient;

		do_action( 'paubox_cf7_api_before_sent_to_api', $email_body );

		$response = $this->api_client->send_mail(
			$mail_from,
			$recipients,
			$subject,
			$email_body,
			$email_attachments
		);

		do_action( 'paubox_cf7_api_after_sent_to_api', $email_body, $response );

		// Abort CF7's default mailer only when Paubox delivered successfully.
		if ( ! \is_wp_error( $response ) ) {
			$abort = true;
		}
	}

	/**
	 * Persists Paubox settings when a CF7 form is saved in the editor.
	 *
	 * @param WPCF7_ContactForm $contact_form The form being saved.
	 * @return void
	 */
	public function save_contact_form_details( WPCF7_ContactForm $contact_form ): void {
		$properties = $contact_form->get_properties();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- CF7 verifies its own nonce before firing this action.
		// Canonicalize to only the on/off checkbox value; never persist arbitrary POST data.
		$properties['wpcf7_api_data']          = [ 'send_to_paubox' => isset( $_POST['wpcf7-sf']['send_to_paubox'] ) ? 'on' : 'off' ];
		$properties['paubox_mail_from']        = sanitize_email( wp_unslash( $_POST['paubox_mail_from'] ?? '' ) );
		$properties['paubox_mail_to']          = sanitize_email( wp_unslash( $_POST['paubox_mail_to'] ?? '' ) );
		$properties['paubox_mail_recipient']   = sanitize_text_field( wp_unslash( $_POST['paubox_mail_recipient'] ?? '' ) );
		$properties['paubox_mail_subject']     = sanitize_text_field( wp_unslash( $_POST['paubox_mail_subject'] ?? '' ) );
		$properties['paubox_mail_template']    = sanitize_textarea_field( wp_unslash( $_POST['paubox_mail_template'] ?? '' ) );
		$properties['paubox_mail_attachments'] = sanitize_textarea_field( wp_unslash( $_POST['paubox_mail_attachments'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$contact_form->set_properties( $properties );
	}

	/**
	 * Registers Paubox-specific properties on a CF7 form object.
	 *
	 * @param array $properties Existing form properties.
	 * @return array Extended properties array with Paubox keys initialised.
	 */
	public function add_sf_properties( array $properties ): array {
		$properties['wpcf7_api_data']          ??= [];
		$properties['paubox_mail_from']        ??= '';
		$properties['paubox_mail_to']          ??= '';
		$properties['paubox_mail_recipient']   ??= '';
		$properties['paubox_mail_subject']     ??= '';
		$properties['paubox_mail_template']    ??= '';
		$properties['paubox_mail_attachments'] ??= '';

		return $properties;
	}
}
