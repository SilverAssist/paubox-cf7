<?php
/**
 * Paubox CF7 Integration
 *
 * @package SilverAssist\PauboxCF7
 * @since 1.0.0
 * @author Silver Assist
 * @version 1.0.1
 * @license Polyform-Noncommercial-1.0.0
 *
 * @wordpress-plugin
 * Plugin Name: Paubox CF7 Integration
 * Plugin URI: https://github.com/SilverAssist/paubox-cf7
 * Description: Integrates Contact Form 7 with the Paubox encrypted email API.
 * Version: 1.0.1
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Author: Silver Assist
 * Author URI: https://silverassist.com
 * License: Polyform-Noncommercial-1.0.0
 * License URI: https://github.com/SilverAssist/paubox-cf7/blob/main/LICENSE
 * Text Domain: paubox-cf7
 * Domain Path: /languages
 * Requires Plugins: contact-form-7
 * Network: false
 * Update URI: https://github.com/SilverAssist/paubox-cf7
 */

defined( 'ABSPATH' ) || exit;

define( 'PAUBOX_CF7_VERSION', '1.0.1' );
define( 'PAUBOX_CF7_FILE', __FILE__ );
define( 'PAUBOX_CF7_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAUBOX_CF7_URL', plugin_dir_url( __FILE__ ) );
define( 'PAUBOX_CF7_BASENAME', plugin_basename( __FILE__ ) );
define( 'PAUBOX_CF7_MIN_PHP_VERSION', '8.2' );
define( 'PAUBOX_CF7_MIN_WP_VERSION', '6.5' );

$paubox_cf7_autoload = realpath( PAUBOX_CF7_DIR . 'vendor/autoload.php' );

if (
	$paubox_cf7_autoload &&
	0 === \strpos( $paubox_cf7_autoload, realpath( PAUBOX_CF7_DIR ) )
) {
	require_once $paubox_cf7_autoload;
} else {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Paubox CF7: Composer autoloader not found. Run composer install.', 'paubox-cf7' );
			echo '</p></div>';
		}
	);
	return;
}

add_action(
	'plugins_loaded',
	static function (): void {
		\SilverAssist\PauboxCF7\Core\Plugin::instance()->init();
	}
);

register_uninstall_hook( __FILE__, [ \SilverAssist\PauboxCF7\Core\Activator::class, 'uninstall' ] );
