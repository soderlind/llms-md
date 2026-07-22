# llms.md

Publish an AI-generated `/llms.md` on your WordPress site so AI assistants and LLM tools can understand what your site is about.

> New to the idea? Read [What is llms.md?](docs/what-is-llms.md).

## Why

AI assistants, chatbots, and research tools increasingly read websites to answer questions. `llms.md` gives them a clean, machine-readable briefing at one predictable address — `https://yoursite.com/llms.md` — so they rely on an accurate overview you control instead of guessing.

## Features

- A ready-to-use `/llms.md` endpoint on your site.
- An AI-written summary of your site's purpose, key topics, and content.
- Automatic background updates when you publish or edit content.
- A daily safety refresh so the file never goes stale.
- Cached responses — no AI call on every visit.
- An admin status page with a one-click rebuild.
- Works on single sites and multisite (including subdirectory installs).

## Requirements

- WordPress 7.0+
- PHP 8.3+
- At least one WordPress AI provider connector configured

## Installation

### From the WordPress plugin directory

1. In wp-admin, go to **Plugins > Add New** and search for **llms.md**.
2. Click **Install Now**, then **Activate**.
3. Configure at least one WordPress AI provider connector.
4. Go to **Settings > llms.md** and click **Regenerate llms.md**.

### From source

```bash
git clone https://github.com/soderlind/llms-md.git
cd llms-md
composer install --no-dev
```

Copy the folder into `wp-content/plugins/` and activate **llms.md**.

## How it works

1. The plugin reads your published content (posts, pages, terms).
2. It asks your configured WordPress AI provider to write a concise Markdown summary.
3. The result is cached and served at `/llms.md` with proper caching headers.
4. It refreshes automatically when content changes, plus once a day.

If a physical `llms.md` file already exists at your site root, the plugin steps aside and serves that instead. Until an AI provider is configured, the endpoint responds with a friendly "not ready yet" status.

## Configuration

Everything lives under **Settings > llms.md**:

- See the current status (provider configured, last update, last error).
- Rebuild the document on demand.
- Run diagnostics — **Check Connector** and **Preview Payload**.

## Documentation

- [What is llms.md?](docs/what-is-llms.md) — background and the convention.
- [Developer guide](docs/DEVELOPER.md) — hooks, provider selection, HTTP details, building, and tests.

## Contributing

Issues and pull requests are welcome. See the [developer guide](docs/DEVELOPER.md) for local setup and tests.

## License

Licensed under [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
