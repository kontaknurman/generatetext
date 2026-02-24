# GenerateText

WordPress plugin — AI content tools powered by Claude, OpenAI, or Kimi API.

## Features

1. **Generate Tags** — AI suggests tags based on article content. Prioritizes existing tags, creates new ones when needed. Auto-assigns to the post.
2. **Suggest Category** — Selects categories from your existing list based on content relevance. Does NOT create new categories.
3. **Generate Title** — Generates or rewrites post title based on article content.
4. **Inline Rewrite** — Select text in the editor, click "AI Rewrite" in the toolbar, and the selected text is replaced with the rewritten version.

Each feature can be individually enabled or disabled from the settings page.

## Supported AI Providers

| Provider | Models | API Docs |
|----------|--------|----------|
| **Claude (Anthropic)** | Opus 4.6, Sonnet 4.6, Haiku 4.5 | console.anthropic.com |
| **OpenAI** | GPT-4o, GPT-4o Mini, GPT-4 Turbo, GPT-3.5 Turbo | platform.openai.com |
| **Kimi (Moonshot AI)** | Moonshot v1 8K/32K/128K | platform.moonshot.cn |

## Setup

1. Upload plugin to `wp-content/plugins/generatetext/`
2. Activate in WordPress admin
3. Go to **Settings > GenerateText**
4. Choose your AI provider (Claude, OpenAI, or Kimi)
5. Enter the API key for the selected provider
6. Choose model and customize prompts
7. Enable/disable individual features as needed

## Settings

- **API Provider** — Choose between Claude, OpenAI, or Kimi
- **API Key** — Provider-specific API key (only the active provider's key is shown)
- **Model** — Provider-specific model selection
- **Max Tokens** — Response limit (256-4096)
- **Post Types** — Select which post types show GenerateText features
- **Feature Toggles** — Enable/disable each feature individually (Generate Tags, Suggest Category, Generate Title, Inline Rewrite)
- **Prompt Templates** — Customizable per feature with placeholders: `{content}`, `{existing_tags}`, `{categories}`, `{text}`

## File Structure

```
generatetext.php              — Main plugin bootstrap
includes/
  class-generatetext-api.php   — Multi-provider API communication (Claude, OpenAI, Kimi)
  class-generatetext-admin.php — Settings page with provider selection & feature toggles
  class-generatetext-ajax.php  — AJAX endpoint handlers
assets/
  js/generatetext-editor.js    — Gutenberg editor integration
  js/generatetext-classic.js   — Classic editor integration
  js/generatetext-tinymce.js   — TinyMCE inline rewrite with typewriter effect
  css/generatetext-admin.css   — Admin styles
```

## Editor Support

- **Gutenberg** — Sidebar panel (GenerateText AI) + inline rewrite via RichText toolbar
- **Classic Editor** — Meta box + TinyMCE toolbar button

## API

- **Claude**: Messages API (`POST /v1/messages`) via `wp_remote_post`
- **OpenAI**: Chat Completions API (`POST /v1/chat/completions`) via `wp_remote_post`
- **Kimi**: OpenAI-compatible Chat Completions API (`POST /v1/chat/completions`) via `wp_remote_post`

No Composer dependencies.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- API key from one of: Anthropic, OpenAI, or Moonshot AI
