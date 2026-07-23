# Developer Guide

Technical reference for the llms.md plugin: how it behaves, the hooks it exposes,
provider selection, the HTTP response, building a release, and running tests.

## How it behaves

- Owns only the `/llms.md` URL (no custom paths).
- Uses cached snapshot regeneration, not per-request generation.
- Defers to a physical web-root `llms.md` file when one exists (passive mode).
- Regenerates on public content changes and via a daily safety rebuild.
- Requires a configured AI connector; serves `503` with `Retry-After` when missing.
- Serves the last known-good snapshot for up to 7 days after generation failures, then returns `503`.
- Regeneration policy: single-flight lock + coalesced rerun + 5-minute minimum successful-run interval, backed by Action Scheduler.

## Integration with WP Core AI

The plugin supports two integration paths:

1. Automatic best-effort via `wp_ai_client_prompt(...)->generate_text()` when available.
2. Filter-based integration for connector-specific behavior.

### Connector availability filter

`llmsmd_ai_connector_configured` — return a boolean to declare whether a usable AI
connector exists. Return `null` (the default) to let the plugin decide.

```php
add_filter('llmsmd_ai_connector_configured', function ($configured) {
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

`llmsmd_generate_document` — return a non-empty Markdown string to supply or
override the generated document.

```php
add_filter('llmsmd_generate_document', function ($document, array $payload, array $context) {
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

## Provider selection

- If the `llmsmd_provider_id` filter returns a non-empty provider ID, that provider is used.
- Else, if `llmsmd_model_id` maps to a registered connector ID, that provider is used.
- Else, the first configured AI provider connector is selected automatically.

## Hooks reference

Filters:

- `llmsmd_ai_connector_configured` (mixed) — override connector detection.
- `llmsmd_generate_document` (`$document`, `array $payload`, `array $context`) — supply or override the generated Markdown.
- `llmsmd_provider_id` (string) — force a specific provider ID.
- `llmsmd_model_id` (string) — map a model ID to a registered connector.
- `llmsmd_exit_after_redirect` (bool) — control `exit()` after admin redirects (useful in tests).

Constants:

- `LLMSMD_DISABLE_BOOTSTRAP` — when `true`, prevents the plugin from bootstrapping (used by unit tests).

## HTTP response

Successful `/llms.md` responses include:

- `Content-Type: text/markdown; charset=utf-8`
- `ETag`, `Last-Modified`, and `Cache-Control`
- `X-LLMS-MD-Generated-At`

A discovery header is also emitted on regular page loads:

```text
Link: <https://example.com/llms.md>; rel="alternate"; type="text/markdown"
```

## Admin & operations

- Status page: **Settings > llms.md** (`manage_options`).
- Actions: manual **Regenerate llms.md**, **Check Connector**, and **Preview Payload**.

## Verifying a build

- Activate the plugin and visit `/llms.md`.
- Confirm the rewrite works and the headers are present (`Content-Type`, `ETag`, `Last-Modified`, `X-LLMS-MD-Generated-At`).
- Confirm the `503` response when no connector is configured.
- Edit published content and confirm the snapshot metadata updates.

## Building the distribution

The plugin is distributed through the WordPress.org plugin directory and uses the
standard WordPress update mechanism — no self-updater is bundled.

Two GitHub workflows build `llms-md.zip`:

- `.github/workflows/on-release-add.zip.yml` — builds and attaches the zip to a published release.
- `.github/workflows/manually-build-zip.yml` — builds on demand and can upload to a tag release.

Both install production dependencies (`composer install --no-dev`) and package the
plugin using the exclusions in `.distignore`. `composer.json` is intentionally shipped
because `vendor/` is bundled (Plugin Check flags a bundled `vendor/` without a `composer.json`).

## Tests

Install dependencies:

```bash
composer install
```

Run DB-free unit tests (Brain Monkey):

```bash
composer test        # or: composer test:unit
```

Run WordPress integration tests (requires a WordPress test database):

```bash
composer test:wp
```

The default integration config is `tests/wp-tests-config.php`. Override DB values with
environment variables:

- `WP_TESTS_DB_NAME`, `WP_TESTS_DB_USER`, `WP_TESTS_DB_PASSWORD`, `WP_TESTS_DB_HOST`
- `WP_PHP_BINARY`, `WP_TESTS_WP_PATH`

With a custom WordPress test library path:

```bash
export WP_TESTS_DIR=/path/to/wordpress-tests-lib
phpunit -c phpunit.xml.dist
```

## AI contribution attribution

When AI tools contribute to this plugin, include attribution in commit messages or
release notes:

```text
Assisted-by: AGENT_NAME:MODEL_VERSION [TOOL1] [TOOL2]
```

Example:

```text
Assisted-by: GitHub Copilot:GPT-5.3-Codex
```
