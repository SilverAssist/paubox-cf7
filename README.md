# Paubox CF7 Integration

WordPress plugin that routes Contact Form 7 submissions through the [Paubox](https://www.paubox.com/) encrypted email API, so form emails are delivered HIPAA-compliant instead of through the site's regular mail transport.

## Features

- **🔒 Encrypted Delivery**: Sends Contact Form 7 emails through the Paubox API instead of `wp_mail()`, so submissions are HIPAA-compliant encrypted in transit
- **🧩 Per-Form Configuration**: Adds a "Paubox Integration" tab to the Contact Form 7 editor — enable Paubox delivery, and configure From, Recipient, Subject, and Message body independently per form
- **🏷️ Mail-Tag Support**: Recipient, subject, and message body fields accept CF7 mail-tags (`[your-name]`, `[your-email]`, etc.), resolved from the actual submission
- **📎 Attachment Support**: Forwards uploaded files referenced in the message template as base64-encoded attachments in the API payload
- **⚙️ Centralized Credentials**: API key and API user are configured once, via Silver Assist Settings Hub (or a standalone settings page when the hub isn't installed) — no per-form credential duplication
- **🔄 Automatic Updates**: Built-in GitHub-based update system via `silverassist/wp-github-updater`
- **🛡️ Fails Safe**: Contact Form 7 must be active for any of this plugin's components to load; without valid API credentials, `send_mail()` returns a `WP_Error` rather than silently dropping the submission

## Requirements

- WordPress 6.5 or higher
- PHP 8.2 or higher
- [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) (required — this plugin does nothing without it)
- A [Paubox](https://www.paubox.com/) account with API credentials

## Installation

### Method 1: WordPress Admin Dashboard (Recommended)

1. Download the latest `paubox-cf7-v{version}.zip` from the [Releases page](https://github.com/SilverAssist/paubox-cf7/releases)
2. In WordPress admin, go to `Plugins` → `Add New` → `Upload Plugin`
3. Choose the downloaded ZIP and click `Install Now`
4. Click `Activate Plugin`

### Method 2: Manual Installation via FTP

1. Extract the downloaded ZIP
2. Upload the extracted `paubox-cf7` folder to `/wp-content/plugins/`
3. Activate the plugin from the WordPress admin `Plugins` page

### Method 3: WP-CLI

```bash
wp plugin install paubox-cf7-v1.0.0.zip --activate
```

## Configuration

1. Go to **Silver Assist Settings Hub → Paubox CF7** (or **Settings → Paubox CF7** if the hub isn't installed)
2. Enter your **Paubox API Key** and **Paubox API User**
3. Save

## Usage

Open any Contact Form 7 form in the editor and switch to the new **Paubox Integration** tab:

| Field | Description |
|-------|--------------|
| Send mail with Paubox? | Enables Paubox delivery for this form |
| From | Sender address for the outgoing email |
| Recipient email Tag | CF7 mail-tag (e.g. `[your-email]`) whose submitted value becomes the recipient |
| Default recipient | Fallback recipient used when the tag above is empty |
| Subject | Email subject — accepts mail-tags |
| Message body | Email body template — accepts mail-tags; unmatched tags are stripped |
| File attachments | Which uploaded-file fields (by tag) to attach to the email |

On submission, the plugin builds the message from the template, resolves attachments, and sends it through the Paubox API instead of Contact Form 7's default mailer.

## Automatic Updates

The plugin checks this GitHub repository for new releases and can update itself directly from the WordPress admin, with no separate configuration required — see the **Check Updates** button next to the plugin's Settings Hub entry.

## Development

```bash
composer install
composer phpcs      # Coding standards
composer phpstan     # Static analysis (level 8)
composer test        # Unit tests
composer test:integration  # Integration tests (requires the WP test suite)
```

## License

Polyform Noncommercial License 1.0.0 — see [LICENSE](LICENSE).

## Support

- Issues: https://github.com/SilverAssist/paubox-cf7/issues
- Source: https://github.com/SilverAssist/paubox-cf7
