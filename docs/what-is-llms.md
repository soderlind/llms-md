# What is llms.md?

`llms.md` is a convention for providing machine-readable documentation at a
well-known URL (`/llms.md`) that helps Large Language Models (LLMs) understand
your website's content and structure.

## Purpose

When AI assistants or LLM-powered tools visit your site, they need context
about what the site offers. A well-crafted `llms.md` file provides:

- **Site overview** – A summary of the site's purpose and audience.
- **Content structure** – Key pages, categories, and navigation paths.
- **Available resources** – APIs, documentation, or downloadable assets.
- **Usage guidelines** – Preferred citation style, licensing, and restrictions.

## Why It Matters

LLMs increasingly power search, research assistants, and automation tools.
By publishing an `llms.md` file you:

1. **Improve discoverability** – Help AI tools surface accurate information.
2. **Control the narrative** – Provide authoritative context, not guesswork.
3. **Enable deeper integration** – Allow LLM agents to interact effectively.

## How This Plugin Helps

The **llms.md WordPress Plugin** automates the creation and maintenance of
your site's `llms.md` endpoint:

- Analyzes publicly published content (posts, pages, terms).
- Uses WordPress's AI connector to generate a structured Markdown summary.
- Caches the result and regenerates automatically when content changes.
- Serves the document with proper HTTP headers for caching and freshness.

## Specification

While `llms.md` is an emerging convention without a formal standard, common
best practices include:

| Section           | Description                                   |
| ----------------- | --------------------------------------------- |
| Title & tagline   | One-line site identification.                 |
| Overview          | 2–3 sentences describing the site's purpose.  |
| Key pages         | Top-level navigation with brief descriptions. |
| Content types     | Categories, tags, or custom taxonomies.       |
| Contact / support | How to reach site owners.                     |
| Licensing         | Content usage rights.                         |

## Further Reading

- [llms.txt Specification](https://llmstxt.org/) – Related `llms.txt` initiative.
- [WordPress AI Connectors](https://developer.wordpress.org/) – WP Core AI docs.
