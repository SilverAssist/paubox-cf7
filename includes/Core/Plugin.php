<?php
/**
 * @package SilverAssist\PauboxCF7
 * @copyright Silver Assist. All rights reserved.
 * @version 1.0.0
 * @since 1.0.0
 * @author Silver Assist
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
