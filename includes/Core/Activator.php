<?php
/**
 * Paubox CF7 Integration — Activation, Upgrade, and Uninstall Lifecycle
 *
 * @package SilverAssist\PauboxCF7\Core
 * @since   1.0.1
 * @version 1.1.0
 */

namespace SilverAssist\PauboxCF7\Core;

use SilverAssist\PauboxCF7\Admin\SettingsPage;
use SilverAssist\PauboxCF7\Service\DeliveryLog;

\defined( 'ABSPATH' ) || exit;

/**
 * Manages the plugin's database schema across activation, upgrade, and
 * uninstall.
 *
 * There is no deactivate() — deactivating leaves the delivery log table and
 * options in place, only uninstall (deleting the plugin) removes them.
 *
 * @since 1.0.1
 */
class Activator {

	/**
	 * Delivery log schema version. Bump whenever DeliveryLog::create_table()'s
	 * SQL changes, so maybe_upgrade() re-runs dbDelta() for existing installs.
	 *
	 * @var string
	 */
	private const DB_VERSION = '1.1.0';

	/**
	 * WordPress option storing the schema version currently installed.
	 *
	 * @var string
	 */
	private const DB_VERSION_OPTION = 'paubox_cf7_db_version';

	/**
	 * Runs on plugin activation: creates the delivery log table.
	 *
	 * @return void
	 */
	public static function activate(): void {
		DeliveryLog::create_table();
		\update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Self-heals the schema for sites that update via the GitHub updater.
	 *
	 * Register_activation_hook() only fires on a fresh (re)activation, never
	 * on an in-place update — so this runs the same activate() routine once
	 * per version bump, on ordinary request lifecycle instead, guarded by a
	 * cheap get_option() so it's a no-op every other request.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( \get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		self::activate();
	}

	/**
	 * Deletes the plugin's options and drops the delivery log table.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		foreach ( SettingsPage::OPTIONS as $option ) {
			\delete_option( $option );
		}
		\delete_option( self::DB_VERSION_OPTION );

		DeliveryLog::drop_table();
	}
}
