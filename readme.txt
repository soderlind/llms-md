=== llms.md ===
Contributors: PerS
Tags: llms, markdown, seo, discovery, connector
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish an AI-written /llms.md so AI assistants and chatbots understand your WordPress site.

== Description ==

AI assistants, chatbots, and research tools increasingly read websites to answer questions. **llms.md** gives them a clean, machine-readable briefing about your site at one predictable address: `https://yoursite.com/llms.md`.

Think of it as a friendly summary written for AI. Instead of guessing what your site is about, tools can read an accurate overview that you control.

The plugin creates and maintains this file for you automatically. It reads your published content, asks your configured WordPress AI provider to write a concise Markdown summary, caches the result, and serves it with the right headers. When you publish or edit content, it refreshes in the background.

= What you get =

* A ready-to-use `/llms.md` endpoint on your site.
* An AI-written overview of your site's purpose, key topics, and content.
* Automatic updates when you add or change published content.
* A daily safety refresh so the file never goes stale.
* Fast responses through caching — no AI call on every visit.
* An admin page to check status and rebuild on demand.

= What you need =

llms.md uses WordPress's built-in AI connectors, so you need at least one AI provider configured on your site. Until a provider is set up, the endpoint politely reports that it isn't ready yet.

= Good to know =

* The plugin only manages the `/llms.md` address — it doesn't change your other pages.
* If you already have a physical `llms.md` file at your site root, the plugin steps aside and serves that instead.
* Works on single sites and multisite, including subdirectory installs.

Learn more about the idea behind it in the [llms.txt initiative](https://llmstxt.org/). Developers can find hooks, provider selection, and build/test details in the plugin's `docs/DEVELOPER.md`.

== Installation ==

1. In your WordPress admin, go to **Plugins > Add New**.
2. Search for **llms.md**.
3. Click **Install Now**, then **Activate**.
4. Configure at least one WordPress AI provider connector.
5. Go to **Settings > llms.md** and click **Regenerate llms.md**.

Manual installation:

1. Upload the `llms-md` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.

== Frequently Asked Questions ==

= What is llms.md? =

It's a simple convention: a Markdown file at `/llms.md` that describes your site for AI tools and LLM agents — similar in spirit to how `robots.txt` guides search crawlers.

= Do I need an AI provider? =

Yes. The plugin generates the summary using WordPress's AI connectors, so you need at least one AI provider configured. Without one, `/llms.md` returns a "not ready yet" response.

= Why do I see an error (HTTP 503) at /llms.md? =

It means the file isn't ready — usually because no AI provider is configured yet, or the first summary hasn't been generated. Configure a provider, then click **Regenerate llms.md** under **Settings > llms.md**.

= Does this slow down my site? =

No. The summary is generated in the background and cached, so visitors and AI tools are served a stored copy. The AI is not called on every request.

= How often does it update? =

Automatically when you publish or edit public content, plus a daily safety refresh. You can also rebuild manually at any time.

= Does it work on multisite? =

Yes. Each site gets its own `/llms.md`, including subdirectory installs (for example `/subsite/llms.md`).

= Can I choose the AI provider or customize the output? =

Yes. Developers can select the provider and customize how the document is generated. See `docs/DEVELOPER.md` in the plugin.

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
