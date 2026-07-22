# llms.md WordPress Plugin

Provides a generated `site.tld/llms.md` endpoint for WordPress using cached AI-driven site analysis.

> **New to llms.md?** See [What is llms.md?](docs/what-is-llms.md) for background.

## Requirements

- WordPress 7.0+
- PHP 8.3+
- Configured WP Core AI connector

## Installation

1. In your WordPress admin, go to **Plugins > Add New** and search for **llms.md**.
2. Click **Install Now**, then **Activate**.
3. Configure at least one WP Core AI provider connector.
4. Go to **Settings > llms.md** and run **Regenerate llms.md**.

To install from source, clone this repository into your WordPress plugins directory, run `composer install --no-dev`, and activate `llms-md`.

## v1 Behavior

- Owns only `/llms.md` (no custom path).
- Uses cached snapshot regeneration, not per-request generation.
- Defers to a physical web-root `llms.md` file when present.
- Regenerates on public content changes plus a daily safety rebuild.
- Requires AI connector; serves `503` with `Retry-After` when missing.
- Serves stale snapshot for up to 7 days after generation failures, then returns `503`.

## Integration With WP Core AI

The plugin supports two integration paths:

1. Automatic best-effort via `wp_ai_client_prompt(...)->generate_text()` when available.
2. Recommended filter-based integration for connector-specific behavior.

### Connector availability filter

```php
add_filter('llms_md_ai_connector_configured', function ($configured) {
    if (is_bool($configured)) {
        return $configured;
    }

    // Replace with your own WP Core AI connector check.
    if (!function_exists('wp_get_connectors')) {
        return false;
    }

    foreach (wp_get_connectors() as $connector) {
        if (($connector['type'] ?? '') !== 'ai_provider') {
            continue;
        }

        $auth = $connector['authentication'] ?? [];
        if (($auth['method'] ?? '') === 'none') {
            return true;
        }
    }

    return false;
});
```

### Generation filter

```php
add_filter('llms_md_generate_document', function ($document, array $payload, array $context) {
    if (is_string($document) && trim($document) !== '') {
        return $document;
    }

    // Replace this with your WP Core AI call.
    if (function_exists('wp_ai_client_prompt')) {
        $prompt = 'Generate llms.md from payload: ' . wp_json_encode($payload);
        $result = wp_ai_client_prompt($prompt)
            ->using_temperature(0.0)
            ->using_candidate_count(1)
            ->generate_text();

        if (is_string($result) && trim($result) !== '') {
            return $result;
        }
    }

    return $document;
}, 10, 3);
```

## Operations

- Admin status page: `Settings -> llms.md`
- Manual rebuild capability: `manage_options`
- Regeneration policy: single-flight lock + coalesced rerun + 5-minute minimum successful-run interval
- Diagnostics panel: `Check Connector` and `Preview Payload` actions in `Settings -> llms.md`

## Provider Selection

- If `llms_md_provider_id` filter returns a non-empty provider ID, that provider is used.
- Else, if `llms_md_model_id` is set to a registered connector ID, that provider is used.
- Else, the first configured AI provider connector is selected automatically.

## GitHub Updates

This plugin is distributed through the WordPress.org plugin directory and uses the standard WordPress update mechanism. No self-updater is bundled.

## Release Workflows

A workflow in `.github/workflows/` can build `llms-md.zip` for GitHub releases:

- `on-release-add.zip.yml` builds `llms-md.zip` and uploads it to published releases.
- `manually-build-zip.yml` builds `llms-md.zip` on demand and can upload to a tag release.

## Verification

- Activate plugin and visit `/llms.md`.
- Confirm rewrite works and headers are present (`Content-Type`, `ETag`, `Last-Modified`, `X-LLMS-MD-Generated-At`).
- Confirm `503` behavior when connector is not configured.
- Trigger content edits and verify scheduled regeneration updates snapshot metadata.

## Tests

This plugin includes a WordPress PHPUnit scaffold in `tests/`.

Install local test dependencies:

```bash
composer install
```

Run DB-free unit tests first (Brain Monkey):

```bash
composer test
```

Equivalent explicit unit command:

```bash
composer test:unit
```

Run tests using vendor-installed wp-phpunit helpers:

```bash
composer test:wp
```

The default test config file is `tests/wp-tests-config.php`. You can override DB values with environment variables:

- `WP_TESTS_DB_NAME`
- `WP_TESTS_DB_USER`
- `WP_TESTS_DB_PASSWORD`
- `WP_TESTS_DB_HOST`
- `WP_PHP_BINARY`
- `WP_TESTS_WP_PATH`

Alternative with a custom WordPress test library path:

```bash
export WP_TESTS_DIR=/path/to/wordpress-tests-lib
phpunit -c phpunit.xml.dist
```

## AI Contribution Attribution

When AI tools contribute to this plugin, include attribution in commit messages or release notes using:

`Assisted-by: AGENT_NAME:MODEL_VERSION [TOOL1] [TOOL2]`

Example:

`Assisted-by: GitHub Copilot:GPT-5.3-Codex`
