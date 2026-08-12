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

use SilverAssist\PauboxCF7\CF7\Integration;
use SilverAssist\PluginKernel\AbstractPlugin;

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
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function init_hooks(): void {}
}
