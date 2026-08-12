<?php
/**
 * Paubox CF7 Integration — Main Plugin Bootstrap
 *
 * Extends AbstractPlugin to register the CF7 integration component
 * and guard against loading when Contact Form 7 is absent.
 *
 * @package SilverAssist\PauboxCF7\Core
 * @since   1.0.0
 * @version 1.0.0
 */

namespace SilverAssist\PauboxCF7\Core;

use SilverAssist\PauboxCF7\Admin\SettingsPage;
use SilverAssist\PauboxCF7\CF7\Integration;
use SilverAssist\PluginKernel\AbstractPlugin;
use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

\defined( 'ABSPATH' ) || exit;

/**
 * Main plugin controller.
 *
 * Singleton access and the priority-ordered component loading loop are
 * inherited from AbstractPlugin — this class only declares which components
 * to load and any plugin-specific setup that runs alongside them.
 *
 * @since 1.0.0
 */
final class Plugin extends AbstractPlugin {

	/**
	 * GitHub Updater instance, null when the package is unavailable.
	 *
	 * @var Updater|null
	 */
	private ?Updater $updater = null;

	/**
	 * {@inheritDoc}
	 */
	public function should_load(): bool {
		return \class_exists( 'WPCF7_ContactForm' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_components(): array {
		return [
			Integration::class,
			SettingsPage::class,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function init_hooks(): void {
		$this->init_updater();
	}

	/**
	 * Returns the GitHub Updater instance, or null when unavailable.
	 *
	 * @return Updater|null
	 */
	public function get_updater(): ?Updater {
		return $this->updater;
	}

	/**
	 * Initialises automatic updates from GitHub releases.
	 *
	 * @return void
	 */
	private function init_updater(): void {
		if ( ! \class_exists( Updater::class ) || ! \is_admin() ) {
			return;
		}

		$config = new UpdaterConfig(
			PAUBOX_CF7_FILE,
			'SilverAssist/paubox-cf7',
			[
				'plugin_slug'        => 'paubox-cf7',
				'plugin_name'        => 'Paubox CF7 Integration',
				'requires_wordpress' => PAUBOX_CF7_MIN_WP_VERSION,
				'requires_php'       => PAUBOX_CF7_MIN_PHP_VERSION,
				'asset_pattern'      => 'paubox-cf7-v{version}.zip',
				'ajax_action'        => 'paubox_cf7_check_version',
				'ajax_nonce'         => 'paubox_cf7_version_nonce',
				'text_domain'        => 'paubox-cf7',
			]
		);

		$this->updater = new Updater( $config );
	}
}
