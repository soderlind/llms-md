# Changelog

All notable changes to this project are documented in this file.

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
