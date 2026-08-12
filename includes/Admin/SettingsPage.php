<?php
/**
 * Paubox CF7 Integration — Admin Settings Page
 *
 * Registers the plugin with Silver Assist Settings Hub and provides
 * the settings form for the Paubox API credentials (key and user).
 *
 * @package SilverAssist\PauboxCF7\Admin
 * @since   1.0.0
 * @version 1.0.0
 */

namespace SilverAssist\PauboxCF7\Admin;

use SilverAssist\PauboxCF7\Core\Plugin;
use SilverAssist\PluginKernel\Interfaces\LoadableInterface;
use SilverAssist\SettingsHub\SettingsHub;

\defined( 'ABSPATH' ) || exit;

/**
 * Integrates with Silver Assist Settings Hub.
 *
 * @since 1.0.0
 */
class SettingsPage implements LoadableInterface {

	/**
	 * WordPress option names managed by this plugin.
	 *
	 * @var string[]
	 */
	private const OPTIONS = [ 'paubox_api_key', 'paubox_api_user' ];

	/** Returns false when not in the admin or when SettingsHub is unavailable. */
	public function should_load(): bool {
		return \is_admin() && \class_exists( SettingsHub::class );
	}

	/** Returns 30 — loads after core (10) and integration (20). */
	public function get_priority(): int {
		return 30;
	}

	/** Registers settings and the Settings Hub admin menu entry. */
	public function init(): void {
		\add_action( 'admin_init', [ $this, 'register_settings' ] );
		// Priority 4 so we register before Settings Hub renders at priority 5.
		\add_action( 'admin_menu', [ $this, 'register_with_hub' ], 4 );
	}

	/**
	 * Registers the two Paubox options with the WordPress Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		foreach ( self::OPTIONS as $option ) {
			\register_setting(
				'paubox_cf7_settings',
				$option,
				[ 'sanitize_callback' => 'sanitize_text_field' ]
			);
		}
	}

	/**
	 * Registers this plugin's settings page with Settings Hub.
	 *
	 * @return void
	 */
	public function register_with_hub(): void {
		if ( ! \class_exists( SettingsHub::class ) ) {
			return;
		}

		$actions = [];

		$updater = Plugin::instance()->get_updater();
		if ( null !== $updater ) {
			$actions[] = [
				'label'    => \__( 'Check Updates', 'paubox-cf7' ),
				'callback' => [ $this, 'render_update_check_script' ],
				'class'    => 'button',
			];
		}

		SettingsHub::get_instance()->register_plugin(
			'paubox-cf7',
			\__( 'Paubox CF7 Integration', 'paubox-cf7' ),
			[ $this, 'render_settings_page' ],
			[
				'description' => \__( 'Delivers Contact Form 7 submissions via the Paubox encrypted-email API.', 'paubox-cf7' ),
				'version'     => PAUBOX_CF7_VERSION,
				'capability'  => 'manage_options',
				'tab_title'   => \__( 'Paubox CF7', 'paubox-cf7' ),
				'plugin_file' => PAUBOX_CF7_FILE,
				'actions'     => $actions,
			]
		);
	}

	/**
	 * Renders the inline update-check button script via wp-github-updater.
	 *
	 * @param string $plugin_slug Plugin slug passed by Settings Hub.
	 * @return void
	 */
	public function render_update_check_script( string $plugin_slug = '' ): void {
		$updater = Plugin::instance()->get_updater();
		if ( null === $updater ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inline JS provided by wp-github-updater.
		echo $updater->enqueueCheckUpdatesScript();
	}

	/**
	 * Renders the Paubox API credentials settings form.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		$updated = isset( $_GET['settings-updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<?php if ( $updated ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php \esc_html_e( 'Settings saved.', 'paubox-cf7' ); ?></p>
			</div>
		<?php endif; ?>
		<form method="post" action="options.php">
			<?php \settings_fields( 'paubox_cf7_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="paubox_api_key"><?php \esc_html_e( 'Paubox API Key', 'paubox-cf7' ); ?></label>
					</th>
					<td>
						<input type="password" id="paubox_api_key" name="paubox_api_key"
							class="regular-text"
							value="<?php echo \esc_attr( (string) \get_option( 'paubox_api_key' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="paubox_api_user"><?php \esc_html_e( 'Paubox API User', 'paubox-cf7' ); ?></label>
					</th>
					<td>
						<input type="text" id="paubox_api_user" name="paubox_api_user"
							class="regular-text"
							value="<?php echo \esc_attr( (string) \get_option( 'paubox_api_user' ) ); ?>" />
					</td>
				</tr>
			</table>
			<?php \submit_button(); ?>
		</form>
		<?php
	}
}
