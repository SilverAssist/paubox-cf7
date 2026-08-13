# Paubox CF7 Integration — Project Context

WordPress plugin that routes Contact Form 7 submissions through the Paubox encrypted email API instead of `wp_mail()`.

| Field | Value |
|-------|-------|
| **Namespace** | `SilverAssist\PauboxCF7` |
| **Text Domain** | `paubox-cf7` |
| **Version** | See `PAUBOX_CF7_VERSION` constant |
| **PHP** | 8.2+ |
| **WordPress** | 6.5+ |
| **Hard dependency** | Contact Form 7 (`Requires Plugins` header; every component's `should_load()` also checks `class_exists('WPCF7_ContactForm')`) |

## Documentation Rule

All project documentation lives in **README.md**, **CHANGELOG.md**, and this file only.
Never create standalone `.md` files (`docs/`, `CONTRIBUTING.md`, `API.md`, etc.).

## Architecture

Built on `silverassist/wp-plugin-kernel`'s `AbstractPlugin`/`LoadableInterface` pattern —
singleton `instance()`, priority-ordered `get_components()`, per-component error isolation.
Bootstrapped from `plugins_loaded` in the main file:

```php
\SilverAssist\PauboxCF7\Core\Plugin::instance()->init();
```

| Class | Priority | Purpose |
|-------|----------|---------|
| `Core\Plugin` | (root) | Loads components below, initializes the GitHub updater |
| `CF7\Integration` | 20 | Registers CF7 hooks (`wpcf7_before_send_mail`, editor panel), delegates delivery to `ApiClient` |
| `Admin\SettingsPage` | 30 | Registers the Paubox API credentials page with Silver Assist Settings Hub (standalone fallback when hub absent) |

`Service\ApiClient` is a plain (non-`LoadableInterface`) service — constructed directly by
`CF7\Integration`, not loaded through the kernel. It builds the CF7 submission into the
Paubox API payload and sends it via `wp_remote_post()`.

`Core\Activator` only has `uninstall()` — deletes `paubox_api_key`/`paubox_api_user`
(`Admin\SettingsPage::OPTIONS`). No `activate()`/`deactivate()`: nothing to seed, no rewrite
rules or scheduled events.

## Plugin-Specific Patterns

### Paubox API Field Names — Exact Casing Required

The attachment payload MUST use `fileName`/`contentType` (camelCase) — Paubox's API does not
recognize snake_case. This has regressed once already (see `Service\ApiClient::get_email_attachments()`);
the `phpcs:ignore` comments on those lines exist for exactly this reason — don't "fix" them to
snake_case to satisfy `WordPress.NamingConventions.ValidVariableName`.

### Mail-Tags

Recipient, subject, and message body fields in the CF7 editor's "Paubox Integration" tab accept
CF7 mail-tags (`[your-name]`, `[your-email]`, etc.), resolved from `WPCF7_Submission::get_posted_data()`
in `ApiClient::get_email_body()`. Unmatched tags are stripped, not left as literal `[tag]` text.

### Settings Hub Integration

`Admin\SettingsPage::should_load()` requires both `is_admin()` and `class_exists(SettingsHub::class)`.
When Settings Hub isn't installed, this plugin currently registers **no** standalone settings page —
unlike some sibling plugins, there is no `add_options_page()` fallback here.

### GitHub Updater

Not published on WordPress.org — `silverassist/wp-github-updater` handles update checks and
installs directly from this repo's GitHub Releases. Configured in `Core\Plugin::init_updater()`,
admin-only. Never add WordPress.org-style update headers beyond `Update URI` (already present,
pointing at this repo — prevents WordPress.org from ever claiming update ownership of this slug).

## TDD / Test Layout

- `tests/Unit/` — pure unit tests (`PluginTest`)
- `tests/Integration/` — `WP_UnitTestCase`-based, real WP test environment
  - `tests/Integration/Service/ApiClientTest.php`
  - `tests/Integration/CF7/IntegrationTest.php`
  - `tests/Integration/Admin/SettingsPageTest.php`
- Run: `composer test` (unit), `composer test:integration` (needs `WP_TESTS_DIR` — see `scripts/install-wp-tests.sh`)

## Quick References

| Item | Value |
|------|-------|
| Main file | `paubox-cf7.php` |
| Namespace | `SilverAssist\PauboxCF7` |
| Text domain | `paubox-cf7` |
| Options | `paubox_api_key`, `paubox_api_user` (see `Admin\SettingsPage::OPTIONS`) |
| Quality checks | `composer phpcs`, `composer phpstan`, `composer test` |
| WP test install | `scripts/install-wp-tests.sh wordpress_test root '' localhost latest` |
| GitHub repo | `SilverAssist/paubox-cf7` |
| Constants | `PAUBOX_CF7_VERSION`, `PAUBOX_CF7_FILE`, `PAUBOX_CF7_DIR`, `PAUBOX_CF7_URL`, `PAUBOX_CF7_BASENAME`, `PAUBOX_CF7_MIN_PHP_VERSION`, `PAUBOX_CF7_MIN_WP_VERSION` |
