<?php
/**
 * Plugin Name: GenerateText
 * Plugin URI: https://github.com/kontaknurman/generatetext
 * Description: AI-powered content tools using Claude API — generate tags, suggest categories, rewrite titles, and inline text rewriting.
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Author: kontaknurman
 * License: GPL v2 or later
 * Text Domain: generatetext
 */

defined('ABSPATH') || exit;

define('GENERATETEXT_VERSION', '1.0.0');
define('GENERATETEXT_PATH', plugin_dir_path(__FILE__));
define('GENERATETEXT_URL', plugin_dir_url(__FILE__));
define('GENERATETEXT_BASENAME', plugin_basename(__FILE__));

require_once GENERATETEXT_PATH . 'includes/class-generatetext-api.php';
require_once GENERATETEXT_PATH . 'includes/class-generatetext-admin.php';
require_once GENERATETEXT_PATH . 'includes/class-generatetext-ajax.php';

final class GenerateText
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        new GenerateText_Admin();
        new GenerateText_Ajax();

        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_classic_editor_assets']);
        add_filter('plugin_action_links_' . GENERATETEXT_BASENAME, [$this, 'add_settings_link']);

        // TinyMCE integration for Classic Editor
        add_filter('mce_buttons', [$this, 'register_tinymce_button']);
        add_filter('mce_external_plugins', [$this, 'register_tinymce_plugin']);
    }

    private function is_allowed_post_type(): bool
    {
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') {
            return false;
        }

        $settings = get_option('generatetext_settings', []);
        $allowed = $settings['post_types'] ?? ['post'];

        return in_array($screen->post_type, $allowed, true);
    }

    public function enqueue_editor_assets(): void
    {
        if (!$this->is_allowed_post_type()) {
            return;
        }

        wp_enqueue_style(
            'generatetext-editor',
            GENERATETEXT_URL . 'assets/css/generatetext-admin.css',
            [],
            GENERATETEXT_VERSION
        );

        wp_enqueue_script(
            'generatetext-editor',
            GENERATETEXT_URL . 'assets/js/generatetext-editor.js',
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-dom-ready', 'wp-rich-text', 'wp-block-editor'],
            GENERATETEXT_VERSION,
            true
        );

        wp_localize_script('generatetext-editor', 'generatetextData', $this->get_js_data());
    }

    public function enqueue_classic_editor_assets(string $hook): void
    {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        if (!$this->is_allowed_post_type()) {
            return;
        }

        // Only load classic editor assets when block editor is NOT active
        if (function_exists('use_block_editor_for_post') && use_block_editor_for_post(get_post())) {
            return;
        }

        wp_enqueue_style(
            'generatetext-classic',
            GENERATETEXT_URL . 'assets/css/generatetext-admin.css',
            [],
            GENERATETEXT_VERSION
        );

        // Print generatetextData early so TinyMCE plugin (loaded via mce_external_plugins) can access it
        $js_data = $this->get_js_data();
        wp_add_inline_script('jquery', 'window.generatetextData = ' . wp_json_encode($js_data) . ';');

        wp_enqueue_script(
            'generatetext-classic',
            GENERATETEXT_URL . 'assets/js/generatetext-classic.js',
            ['jquery'],
            GENERATETEXT_VERSION,
            true
        );

        wp_localize_script('generatetext-classic', 'generatetextData', $js_data);
    }

    private function get_js_data(): array
    {
        $settings = get_option('generatetext_settings', []);
        return [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('generatetext_nonce'),
            'features' => [
                'tags'     => ($settings['feature_tags'] ?? '1') === '1',
                'category' => ($settings['feature_category'] ?? '1') === '1',
                'title'    => ($settings['feature_title'] ?? '1') === '1',
                'rewrite'  => ($settings['feature_rewrite'] ?? '1') === '1',
            ],
        ];
    }

    public function register_tinymce_button(array $buttons): array
    {
        if (!$this->is_allowed_post_type()) {
            return $buttons;
        }
        $settings = get_option('generatetext_settings', []);
        if (($settings['feature_rewrite'] ?? '1') !== '1') {
            return $buttons;
        }
        $buttons[] = 'generatetext_rewrite';
        return $buttons;
    }

    public function register_tinymce_plugin(array $plugins): array
    {
        if (!$this->is_allowed_post_type()) {
            return $plugins;
        }
        $settings = get_option('generatetext_settings', []);
        if (($settings['feature_rewrite'] ?? '1') !== '1') {
            return $plugins;
        }
        $plugins['generatetext_rewrite'] = GENERATETEXT_URL . 'assets/js/generatetext-tinymce.js';
        return $plugins;
    }

    public function add_settings_link(array $links): array
    {
        $url = admin_url('options-general.php?page=generatetext');
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'generatetext') . '</a>');
        return $links;
    }

    public static function activate(): void
    {
        $defaults = [
            'api_provider'     => 'claude',
            'api_key'          => '',
            'model'            => 'claude-sonnet-4-6',
            'openai_api_key'   => '',
            'openai_model'     => 'gpt-4o',
            'kimi_api_key'     => '',
            'kimi_model'       => 'moonshot-v1-8k',
            'max_tokens'       => 1024,
            'post_types'       => ['post'],
            'feature_tags'     => '1',
            'feature_category' => '1',
            'feature_title'    => '1',
            'feature_rewrite'  => '1',
            'prompt_tags'      => 'Based on the following article content, suggest relevant tags. Use existing tags when possible: {existing_tags}. If needed, suggest new tags. Return ONLY a JSON array of tag names, nothing else.\n\nArticle:\n{content}',
            'prompt_category'  => 'Based on the following article content, select the most appropriate categories from this list: {categories}. Return ONLY a JSON array of category names that best match the content.\n\nArticle:\n{content}',
            'prompt_title'     => 'Based on the following article content, generate an SEO-friendly and engaging title. Return ONLY the title text, nothing else.\n\nArticle:\n{content}',
            'prompt_rewrite'   => 'Rewrite the following text to be clearer, more engaging, and well-structured. Maintain the same meaning and tone. Return ONLY the rewritten text, nothing else.\n\nText:\n{text}',
        ];

        if (!get_option('generatetext_settings')) {
            add_option('generatetext_settings', $defaults);
        }
    }

    public static function deactivate(): void
    {
        // Cleanup if needed
    }
}

register_activation_hook(__FILE__, [GenerateText::class, 'activate']);
register_deactivation_hook(__FILE__, [GenerateText::class, 'deactivate']);

add_action('plugins_loaded', [GenerateText::class, 'instance']);
