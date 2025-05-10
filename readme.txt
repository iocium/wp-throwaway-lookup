=== throwaway.cloud E-Mail Check for WordPress ===
Contributors: iocium
Tags: spam, email validation, disposable email, gdpr, privacy, registration, comment
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Protect your WordPress site by detecting and blocking disposable email addresses using throwaway.cloud. Ensure GDPR/CCPA compliance with full logging, filtering, export and deletion tools.

== Description ==

throwaway.cloud E-Mail Check for WordPress protects your WordPress site from temporary or disposable email abuse.

= Features =
* Blocks disposable emails via [throwaway.cloud](https://throwaway.cloud) API
* Prevents abuse in registration, comments, and forms
* Configurable log granularity (full, domain-only, or none)
* Email/domain allow list
* Admin interface for searching, filtering, and exporting logs
* GDPR/CCPA deletion and export tools
* Developer-friendly action/filter hooks
* Full PHPUnit test suite with Codecov integration

== Installation ==

1. Upload the plugin ZIP via Plugins → Add New → Upload Plugin
2. Activate the plugin
3. Configure via Settings → Throwaway Lookup

== Frequently Asked Questions ==

= What is throwaway.cloud? =
An open API that identifies known disposable/temporary email domains and addresses.

= Is this plugin GDPR compliant? =
Yes — you can control what gets logged (or disable logging entirely). Data can be deleted or exported by subject.

= Can I allow specific domains? =
Yes. Add them to the allow list under Settings.

= Will it slow down my site? =
No — requests to the API are fast, and results are cached temporarily.

== Screenshots ==
1. Settings panel for logging and allow list
2. Admin log viewer with filters and GDPR tools

== Changelog ==

= 1.0.0 =
* Added admin UI with log viewer, filters, export and deletion tools
* Added configurable logging granularity
* Added hook-based subject export and deletion tools
* Full test suite with coverage support

== License ==

MIT License – see LICENSE file for details.
