# Context Glossary

## llms.md Document
The Markdown document served at the site-level path `/llms.md`, intended for machine consumption.

## Canonical Route Ownership
The routing rule that defines which URL the plugin is responsible for serving.
Current value: strict ownership of `/llms.md` only in v1, with no custom path support.

## Generation Mode
How the `llms.md Document` is produced over time.
Current value: cached artifact regeneration.

## Cached Artifact Regeneration
A model where `llms.md Document` content is rebuilt on domain events or scheduled jobs and served from stored output, instead of being generated on each request.

## Last Known Good Snapshot
The most recent valid generated artifact that can be served when a new generation attempt fails.

## Physical File Precedence
A precedence rule where an existing web-root file at `/llms.md` takes authority over plugin rewrite output.
Current value: plugin defers and becomes passive, while showing an admin notice about passive mode.

## Site Scope
The tenancy boundary for analysis, generation, caching, and serving.
Current value: per-site scope in multisite (one artifact lifecycle per site).

## Connector Availability Gate
A serving gate that requires a configured WP Core AI connector before the plugin can provide AI-derived `llms.md` content.
Current value: if missing, serve `503 Service Unavailable` with a short status body and `Retry-After`, plus admin error state.

## Staleness Budget
The maximum age of `Last Known Good Snapshot` that may still be served after regeneration failures.
Current value: 7 days. After that, serve 503 until regeneration succeeds.

## Regeneration Trigger Set
The domain events that enqueue or run artifact regeneration.
Current value: public post type publish/update/delete and term relationship changes in v1, plus a daily safety rebuild cron.

## Success Response Contract
The HTTP contract when `/llms.md` is served successfully.
Current value: `Content-Type: text/markdown; charset=utf-8`, `Cache-Control: public, max-age=300`, with `ETag`, `Last-Modified`, and `X-LLMS-MD-Generated-At`.

## Analysis Data Boundary
The content eligibility rule for what may be sent to the AI connector.
Current value: only publicly published content in v1, excluding private, draft, future, password-protected, revision, and trash states.

## Analysis Payload Shape
The per-item information extracted for AI analysis.
Current value: bounded representation with title, canonical permalink, excerpt, headings, and first 2,000 characters of cleaned content.

## Enrichment Scope
Additional metadata included in the analysis payload.
Current value: include categories, tags, and featured-image alt text; exclude outbound link crawling.

## Deterministic Generation Policy
Rules that minimize output variance between regenerations.
Current value: low-variance generation settings, pinned model ID, and snapshot metadata storing model and prompt version.

## Regeneration Authority
The capability boundary for manually initiating regeneration.
Current value: only users with `manage_options` in v1.

## Regeneration Concurrency Policy
Rules for handling overlapping regeneration requests.
Current value: single-flight lock, trigger coalescing, and a 5-minute minimum interval between successful runs.