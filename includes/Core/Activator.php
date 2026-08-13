<?php
/**
 * Paubox CF7 Integration — Uninstall Cleanup
 *
 * @package SilverAssist\PauboxCF7\Core
 * @since   1.0.1
 * @version 1.0.1
 */

namespace SilverAssist\PauboxCF7\Core;

use SilverAssist\PauboxCF7\Admin\SettingsPage;

\defined( 'ABSPATH' ) || exit;

/**
 * Removes the plugin's own options on uninstall.
 *
 * There is no activate()/deactivate() here — the plugin has no default
 * options to seed and no rewrite rules or scheduled events to manage, so
 * there is nothing to do on either of those lifecycle events.
 *
 * @since 1.0.1
 */
class Activator {

	/**
	 * Deletes the plugin's options.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		foreach ( SettingsPage::OPTIONS as $option ) {
			\delete_option( $option );
		}
	}
}
