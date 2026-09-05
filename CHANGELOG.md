# Changelog

All notable changes to Paubox CF7 Integration will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### 🐛 Fixed

- Contact Form 7 always reports a Paubox-routed submission's abort of its own mailer as status `aborted` (never `mail_sent`), even when Paubox delivered the email successfully — every successful submission was surfaced to the site as an error. `CF7\Integration` now reconciles the `wpcf7_submission_result` filter's status back to `mail_sent` whenever the abort was caused by a confirmed Paubox delivery.

### ✨ Added

- Lightweight delivery logging: each Paubox send attempt now records `form_id`, `success`, `http_code`, and `error_message` (metadata only, never the email body or attachments) to the debug log with a `[Paubox CF7]` prefix, for at-a-glance delivery monitoring.

## [1.0.1] - 2026-08-17

### 📝 Documentation

- Added a Trademark Notice & Compliance section to the README, covering both the Paubox and Contact Form 7 trademarks (independent-integration disclaimer, no affiliation/endorsement)

## [1.0.0] - 2026-08-13

### ✨ Added

- Initial release: routes Contact Form 7 submissions through the Paubox encrypted email API instead of `wp_mail()`
- Per-form "Paubox Integration" tab in the Contact Form 7 editor — enable/disable delivery, and configure From, Recipient (mail-tag or fixed default), Subject, and Message body independently per form
- Mail-tag resolution for recipient, subject, and message body, matching Contact Form 7's own tag syntax
- File-attachment forwarding: uploaded files referenced in the message template are attached to the outgoing email
- Centralized API credential configuration via Silver Assist Settings Hub, with a standalone settings page fallback when the hub isn't installed
- Built on `silverassist/wp-plugin-kernel`'s `AbstractPlugin`/`LoadableInterface` pattern — the whole plugin (and each of its components) is a no-op when Contact Form 7 isn't active
- Automatic updates from GitHub releases via `silverassist/wp-github-updater`
