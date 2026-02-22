# GenerateText - Development Guidelines

## Security
- All AJAX handlers must verify nonce (`check_ajax_referer`) and capability (`current_user_can`)
- Sanitize all inputs: `sanitize_text_field`, `wp_kses_post`, `absint`
- Escape all outputs: `esc_html`, `esc_attr`, `esc_url`, `wp_json_encode`
- API key stored in `wp_options`, never exposed to frontend
- No direct DB queries — use WordPress API functions only

## PHP Requirements
- PHP 8.0+ (typed properties, named arguments, match, null-safe operator)
- Follow WordPress Coding Standards
- Use strict type declarations where applicable

## Performance
- No external dependencies — uses `wp_remote_post` instead of Composer packages
- Scripts loaded only on post editor screens
- Content truncated to 8000 chars before API calls to reduce token usage
- No database tables — uses `wp_options` for settings
- AJAX-only API calls — no blocking on page load
