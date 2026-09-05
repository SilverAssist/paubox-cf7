<?php
/**
 * Paubox CF7 Integration — Delivery Log Repository
 *
 * Persists a short history of Paubox delivery attempts (success/failure,
 * HTTP status, error message) to a dedicated table, so admins can see
 * recent deliveries/failures at a glance from the settings page instead of
 * digging through the debug log.
 *
 * @package SilverAssist\PauboxCF7\Service
 * @since   1.1.0
 * @version 1.1.0
 */

namespace SilverAssist\PauboxCF7\Service;

\defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the `{$wpdb->prefix}paubox_cf7_delivery_log` table.
 *
 * A plain (non-LoadableInterface) service, like ApiClient — nothing here
 * needs constructing, so every method is static; the table name is the
 * only "state", and that's derived from the global $wpdb on every call.
 *
 * @since 1.1.0
 */
class DeliveryLog {

	/**
	 * Table name, without the $wpdb prefix.
	 *
	 * @var string
	 */
	private const TABLE_SUFFIX = 'paubox_cf7_delivery_log';

	/**
	 * Returns the fully-prefixed table name (e.g. `wp_paubox_cf7_delivery_log`).
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Creates (or updates) the delivery log table.
	 *
	 * Safe to call on every activation/upgrade — dbDelta() only applies
	 * schema differences and is itself idempotent when the table already
	 * matches. Called from Core\Activator, never directly by this plugin's
	 * request-time code.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		if ( ! \function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// dbDelta() is whitespace-sensitive: each field on its own line, two
		// spaces after "PRIMARY KEY". See wp-admin/includes/upgrade.php.
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			success TINYINT(1) NOT NULL DEFAULT 0,
			http_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			error_message TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drops the delivery log table, if it exists.
	 *
	 * Called from Core\Activator::uninstall() — this plugin cleans up
	 * everything it created, including its own table.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table_name = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only (not user input); identifiers can't go through $wpdb->prepare().
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	/**
	 * Records one Paubox delivery attempt.
	 *
	 * Metadata only — never the email body or attachments, which may
	 * contain PHI for this HIPAA-relevant delivery path.
	 *
	 * @param int    $form_id       The CF7 form ID being submitted.
	 * @param bool   $success       Whether Paubox accepted the message.
	 * @param int    $http_code     The HTTP status Paubox returned (0 when the request never reached Paubox).
	 * @param string $error_message The WP_Error message on failure, empty string on success.
	 * @return void
	 */
	public static function record( int $form_id, bool $success, int $http_code, string $error_message = '' ): void {
		global $wpdb;

		$wpdb->insert(
			self::table_name(),
			[
				'form_id'       => $form_id,
				'success'       => $success ? 1 : 0,
				'http_code'     => $http_code,
				'error_message' => $error_message,
				'created_at'    => \current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%d', '%s', '%s' ]
		);
	}

	/**
	 * Returns the most recent delivery attempts, newest first.
	 *
	 * @param int $limit Maximum number of rows to return.
	 * @return array<int, object{id: int, form_id: int, success: int, http_code: int, error_message: string, created_at: string}> Delivery log rows.
	 */
	public static function get_recent( int $limit = 20 ): array {
		global $wpdb;

		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only (not user input); $limit is passed through prepare() below.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d", $limit ) );
	}
}
