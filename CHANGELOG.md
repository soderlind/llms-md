# Changelog

All notable changes to this project are documented in this file.

## 1.0.0 - 2026-07-22

### Added
- Internationalized all admin UI strings with the `llms-md` text domain.
- Expanded `.distignore` to exclude development files from the distributed package.

### Changed
- Raised the minimum required WordPress version to 7.0 (WP Core AI requirement).
- Sanitized and unslashed all request superglobals (request URI, conditional-request headers, document root, admin notices).
- Updated installation instructions for the WordPress.org plugin directory.

### Removed
- Bundled GitHub self-updater and its dependency, for WordPress.org compliance.

## 0.4.0 - 2026-06-23

### Changed
- Improved admin Payload Preview presentation with a closable panel.
- Added syntax-highlighted JSON rendering for payload preview output.
- Aligned preview container styling with WordPress default metabox (`postbox`) framing.

## 0.3.0 - 2026-06-23

### Added
- GitHub issue templates for bug reports and feature requests.
- GitHub issue config to disable blank issues.
- Bundled Action Scheduler via Composer (`woocommerce/action-scheduler`).

### Changed
- Replaced WP-Cron-based regeneration scheduling with Action Scheduler (with safe fallback support).
- Improved manual regeneration UX in the admin screen.
- Added an indeterminate in-page progress bar shown during manual regeneration.

### Fixed
- Reduced delay before regeneration starts after manual trigger.

## 0.2.0 - 2026-06-23

### Changed
- Updated installation instructions in README.md and readme.txt.

## 0.1.0 - 2026-06-23

### Added
- Initial plugin implementation for serving site-level llms.md.
- Cached artifact generation model with regeneration triggers.
- Public-content bounded analysis payload and metadata enrichment.
- WP Core AI generation path using wp_ai_client_prompt.
- Connector availability gate and explicit 503 behavior with Retry-After.
- Last-known-good snapshot serving with staleness budget enforcement.
- Physical llms.md file precedence with passive-mode admin notice.
- Admin settings page with status table and manual regeneration action.
- Diagnostics actions for connector checks and payload preview.
- Provider auto-selection logic with filter overrides.
- PHPUnit test scaffold with wp-phpunit and phpunit-polyfills support.

### Changed
- Improved route handling for multisite subdirectory requests.
- Added tolerant request URI normalization for repeated-slash paths.
- Added early parse_request interception for llms.md serving robustness.

### Notes
- Current plugin version is 0.1.0.
