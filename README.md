# GenerateText

WordPress plugin — AI content tools powered by Claude API.

## Features

1. **Generate Tags** — AI suggests tags based on article content. Prioritizes existing tags, creates new ones when needed. Auto-assigns to the post.
2. **Suggest Category** — Selects categories from your existing list based on content relevance. Does NOT create new categories.
3. **Generate Title** — Generates or rewrites post title based on article content.
4. **Inline Rewrite** — Select text in the editor, click "AI Rewrite" in the toolbar, and the selected text is replaced with the rewritten version.

## Setup

1. Upload plugin to `wp-content/plugins/generatetext/`
2. Activate in WordPress admin
3. Go to **Settings > GenerateText**
4. Enter your Anthropic API key
5. Choose model and customize prompts

## Settings

- **API Key** — Anthropic API key (from console.anthropic.com)
- **Model** — Claude Opus 4.6 / Sonnet 4.6 / Haiku 4.5
- **Max Tokens** — Response limit (256-4096)
- **Prompt Templates** — Customizable per feature with placeholders: `{content}`, `{existing_tags}`, `{categories}`, `{text}`

## File Structure

```
generatetext.php              — Main plugin bootstrap
includes/
  class-generatetext-api.php   — Claude API communication
  class-generatetext-admin.php — Settings page
  class-generatetext-ajax.php  — AJAX endpoint handlers
assets/
  js/generatetext-editor.js    — Gutenberg editor integration
  js/generatetext-classic.js   — Classic editor integration
  css/generatetext-admin.css   — Admin styles
```

## Editor Support

- **Gutenberg** — Sidebar panel (GenerateText AI) + inline rewrite via RichText toolbar
- **Classic Editor** — Meta box + TinyMCE toolbar button

## API

Uses Claude Messages API (`POST /v1/messages`) via `wp_remote_post`. No Composer dependencies.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Anthropic API key
