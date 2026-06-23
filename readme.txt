=== llms.md ===
Contributors: persoderlind
Tags: ai, llms, markdown, seo, discovery
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 0.1.0
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

1. Upload the plugin folder to /wp-content/plugins/.
2. Activate the plugin through Plugins in wp-admin.
3. Ensure at least one WP Core AI provider connector is configured.
4. Visit Settings -> llms.md and run Regenerate llms.md.
5. Verify endpoint output at /llms.md.

== Frequently Asked Questions ==

= Why do I get HTTP 503 at /llms.md? =

Usually because no AI connector is configured yet, or no snapshot has been generated.

= Does this work on multisite subdirectory installs? =

Yes. The endpoint is handled per-site and supports subsite paths such as /subsite14/llms.md.

= Can I choose the AI provider? =

Yes. Use the llms_md_provider_id filter, or llms_md_model_id if it maps to a registered connector.

== Changelog ==

= 0.1.0 =
* Initial release.
* Added cached llms.md generation and serving.
* Added connector-gated generation with failure handling.
* Added multisite subdirectory request handling for llms.md.
* Added admin status page, diagnostics actions, and manual rebuild.
* Added PHPUnit scaffolding and integration tests.
