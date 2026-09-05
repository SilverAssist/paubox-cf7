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
| `Core\Plugin` | (root) | Loads components below, initializes the GitHub updater, runs `Activator::maybe_upgrade()` |
| `CF7\Integration` | 20 | Registers CF7 hooks (`wpcf7_before_send_mail`, `wpcf7_submission_result`, editor panel), delegates delivery to `ApiClient`, logs each attempt via `DeliveryLog` |
| `Admin\SettingsPage` | 30 | Registers the Paubox API credentials page (+ a "Recent Deliveries" table from `DeliveryLog`) with Silver Assist Settings Hub (standalone fallback when hub absent) |

`Service\ApiClient` and `Service\DeliveryLog` are plain (non-`LoadableInterface`) services —
`ApiClient` is constructed directly by `CF7\Integration`, not loaded through the kernel; it
builds the CF7 submission into the Paubox API payload and sends it via `wp_remote_post()`.
`DeliveryLog` is a static-methods-only table gateway (no state to construct) for the
`{$wpdb->prefix}paubox_cf7_delivery_log` table — records `form_id`/`success`/`http_code`/
`error_message`/`created_at` per Paubox send attempt, metadata only, never the email body.

`Core\Activator` manages that table's lifecycle: `activate()` (registered via
`register_activation_hook()`) creates it and stores `paubox_cf7_db_version`; `maybe_upgrade()`
(called from `Core\Plugin::init_hooks()` on every request) re-runs `activate()` once per schema
version bump, self-healing sites that update via the GitHub updater instead of reactivating;
`uninstall()` deletes `paubox_api_key`/`paubox_api_user` (`Admin\SettingsPage::OPTIONS`),
`paubox_cf7_db_version`, and drops the table. No `deactivate()`: deactivating leaves everything
in place, only uninstall (deleting the plugin) tears it down.

### CF7's Abort Semantics — `wpcf7_submission_result`, Not Just `wpcf7_before_send_mail`

`CF7\Integration::send_data_to_api()` sets `$abort = true` on a successful Paubox delivery, to
skip CF7's own mailer — but CF7 core always reports *any* abort as status `aborted`, never
`mail_sent`, regardless of why. `reconcile_aborted_status()` (hooked on `wpcf7_submission_result`,
applied after CF7 finalizes status/message) rewrites `aborted` → `mail_sent` only when this
request's Paubox delivery is confirmed (tracked via the `$paubox_delivered` instance property) —
never for an abort from another plugin or a failed Paubox send. See CHANGELOG's `[Unreleased]`
entry for the bug this fixes (WEB-1180).

### Custom-Table Tests Need a Non-`WP_UnitTestCase` Class

`WP_UnitTestCase` silently rewrites `CREATE TABLE`/`DROP TABLE` queries into `CREATE`/`DROP
TEMPORARY TABLE` for the duration of every test (its own mechanism for keeping custom-table DDL
from leaking into the shared test database) — so `DeliveryLog::create_table()`/`drop_table()`
calls made *inside* a `WP_UnitTestCase` test only ever touch a phantom temporary table, never the
real one. `Core\ActivatorSchemaTest` extends plain `PHPUnit\Framework\TestCase` instead
specifically to verify that DDL for real; everything else (`DeliveryLogTest`, `SettingsPageTest`,
`CF7\IntegrationTest`) just needs the table to exist, which `tests/bootstrap.php` guarantees once,
before any test's transaction wrapper is installed.

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
  - `tests/Integration/Service/DeliveryLogTest.php`
  - `tests/Integration/CF7/IntegrationTest.php`
  - `tests/Integration/Admin/SettingsPageTest.php`
  - `tests/Integration/Core/ActivatorTest.php` — option-level behavior only (`WP_UnitTestCase`)
  - `tests/Integration/Core/ActivatorSchemaTest.php` — real CREATE/DROP TABLE, plain `TestCase` (see "Custom-Table Tests" above)
- Run: `composer test` (unit), `composer test:integration` (needs `WP_TESTS_DIR` — see `scripts/install-wp-tests.sh`)

## Quick References

| Item | Value |
|------|-------|
| Main file | `paubox-cf7.php` |
| Namespace | `SilverAssist\PauboxCF7` |
| Text domain | `paubox-cf7` |
| Options | `paubox_api_key`, `paubox_api_user` (see `Admin\SettingsPage::OPTIONS`), `paubox_cf7_db_version` (schema version, see `Core\Activator`) |
| Table | `{$wpdb->prefix}paubox_cf7_delivery_log` (see `Service\DeliveryLog`) |
| Quality checks | `composer phpcs`, `composer phpstan`, `composer test` |
| WP test install | `scripts/install-wp-tests.sh wordpress_test root '' localhost latest` |
| GitHub repo | `SilverAssist/paubox-cf7` |
| Constants | `PAUBOX_CF7_VERSION`, `PAUBOX_CF7_FILE`, `PAUBOX_CF7_DIR`, `PAUBOX_CF7_URL`, `PAUBOX_CF7_BASENAME`, `PAUBOX_CF7_MIN_PHP_VERSION`, `PAUBOX_CF7_MIN_WP_VERSION` |
