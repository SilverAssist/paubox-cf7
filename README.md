# Paubox CF7 Integration

WordPress plugin that routes Contact Form 7 submissions through the [Paubox](https://www.paubox.com/) encrypted email API, so form emails are delivered HIPAA-compliant instead of through the site's regular mail transport.

> **📢 Trademark Notice:** This plugin integrates Contact Form 7 with the Paubox encrypted email API. "Paubox" is a trademark of Paubox, Inc. "Contact Form 7" is a trademark of Rock Lobster, LLC. This plugin is an independent integration and is not affiliated with, endorsed by, or sponsored by Paubox, Inc. or the Contact Form 7 project / Rock Lobster, LLC. We use "CF7" (an unofficial abbreviated form) in our plugin name in compliance with the [Contact Form 7 trademark policy](https://contactform7.com/trademark-policy/).

---

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

## ™️ Trademark Notice & Compliance

### Paubox

**"Paubox"** is a trademark of **Paubox, Inc.**, the company behind the Paubox HIPAA-compliant encrypted email service this plugin integrates with.

This plugin (**Paubox CF7 Integration**) is an **independent integration** — it is not affiliated with, endorsed by, or sponsored by Paubox, Inc. We reference "Paubox" only to accurately describe which service this plugin connects to.

### Contact Form 7

**"Contact Form 7"** is a registered trademark of **Rock Lobster, LLC.**, the company behind the Contact Form 7 WordPress plugin. We comply with the [Contact Form 7 trademark policy](https://contactform7.com/trademark-policy/):

✅ **Plugin Name**: We use "CF7" (an unofficial abbreviated form permitted by the policy) instead of "Contact Form 7" in our plugin name
✅ **No Affiliation**: This plugin is not affiliated with, endorsed by, or sponsored by Contact Form 7 or Rock Lobster, LLC
✅ **Documentation**: We mention "Contact Form 7" only for reference and compatibility information

### Our Relationship with Paubox and Contact Form 7

- **What we are**: An independent integration plugin that connects Contact Form 7 to the Paubox encrypted email API
- **What we are NOT**: An official Paubox or Contact Form 7 product, affiliate, or endorsed extension
- **Our purpose**: To let Contact Form 7 users route their form submissions through Paubox for HIPAA-compliant encrypted delivery

### Acknowledgment

We are grateful to Paubox, Inc. for their encrypted email API, and to Rock Lobster, LLC and the Contact Form 7 development team for creating and maintaining the Contact Form 7 plugin that makes this integration possible.

## Support

- Issues: https://github.com/SilverAssist/paubox-cf7/issues
- Source: https://github.com/SilverAssist/paubox-cf7
