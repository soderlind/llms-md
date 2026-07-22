=== llms.md ===
Contributors: PerS
Tags: llms, markdown, seo, discovery, connector
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate and serve /llms.md from cached WordPress site analysis using WP Core AI connectors.

== Description ==

llms.md provides a site-level /llms.md endpoint designed for machine consumption.

The plugin uses cached artifact regeneration rather than per-request AI generation.

Key behavior:

* Canonical endpoint at /llms.md.
* Supports multisite subdirectory paths and repeated slash requests (for example /subsite14//llms.md).
* Defers to an existing physical web-root llms.md file (passive mode).
* Requires a configured WP Core AI connector.
* Returns 503 with Retry-After when connector is missing or no valid snapshot is available.
* Serves stale last-known-good output for up to 7 days after generation failures.
* Includes admin diagnostics and manual regeneration controls.

== Installation ==

1. In your WordPress admin, go to **Plugins > Add New**.
2. Search for **llms.md**.
3. Click **Install Now**, then **Activate**.
4. Configure at least one WP Core AI provider connector.
5. Go to **Settings > llms.md** and run **Regenerate llms.md**.

Manual installation:

1. Upload the `llms-md` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.

== Frequently Asked Questions ==

= Why do I get HTTP 503 at /llms.md? =

Usually because no AI connector is configured yet, or no snapshot has been generated.

= Does this work on multisite subdirectory installs? =

Yes. The endpoint is handled per-site and supports subsite paths such as /subsite14/llms.md.

= Can I choose the AI provider? =

Yes. Use the llms_md_provider_id filter, or llms_md_model_id if it maps to a registered connector.

== Development Tests ==

For local and CI defaults, use mocked unit tests:

* composer test

Run WordPress integration tests only when a WordPress test database/environment is available:

* composer test:wp

== Changelog ==

= 1.0.0 =
* Removed the bundled GitHub self-updater and its dependency for WordPress.org compliance.
* Raised the minimum required WordPress version to 7.0 (WP Core AI requirement).
* Sanitized and unslashed all request superglobals (request URI, conditional-request headers, document root, admin notices).
* Internationalized all admin UI strings with the llms-md text domain.
* Updated installation instructions for the WordPress.org plugin directory.
* Expanded .distignore to exclude development files from the distributed package.
* Passed WordPress Plugin Check with no findings in the distributed plugin.

= 0.4.0 =
* Improved admin Payload Preview presentation with a closable panel.
* Added syntax-highlighted JSON rendering for payload preview output.
* Aligned preview container styling with WordPress default metabox framing.

= 0.3.0 =
* Added GitHub issue templates for bug reports and feature requests.
* Disabled blank GitHub issues.
* Replaced WP-Cron-based regeneration scheduling with Action Scheduler.
* Added bundled Action Scheduler dependency via Composer.
* Improved manual regeneration UX with an in-page progress indicator.
* Reduced startup delay for manual regeneration.

= 0.2.0 =
* Updated installation instructions in README.md and readme.txt.

= 0.1.0 =
* Initial release.
* Added cached llms.md generation and serving.
* Added connector-gated generation with failure handling.
* Added multisite subdirectory request handling for llms.md.
* Added admin status page, diagnostics actions, and manual rebuild.
* Added PHPUnit scaffolding and integration tests.
