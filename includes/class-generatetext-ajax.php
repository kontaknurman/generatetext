<?php

defined('ABSPATH') || exit;

class GenerateText_Ajax
{
    public function __construct()
    {
        add_action('wp_ajax_generatetext_tags', [$this, 'handle_generate_tags']);
        add_action('wp_ajax_generatetext_categories', [$this, 'handle_suggest_categories']);
        add_action('wp_ajax_generatetext_title', [$this, 'handle_generate_title']);
        add_action('wp_ajax_generatetext_rewrite', [$this, 'handle_rewrite_text']);
    }

    private function verify_request(): void
    {
        if (!check_ajax_referer('generatetext_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'generatetext')], 403);
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'generatetext')], 403);
        }
    }

    public function handle_generate_tags(): void
    {
        $this->verify_request();

        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        if (empty($content)) {
            wp_send_json_error(['message' => __('No content provided.', 'generatetext')]);
        }

        // Get existing tags
        $existing_tags = get_tags(['hide_empty' => false, 'fields' => 'names']);
        if (is_wp_error($existing_tags)) {
            $existing_tags = [];
        }

        $api = new GenerateText_API();
        $result = $api->generate_tags($content, $existing_tags);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['error']]);
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $tag_ids = [];
        $tag_names = [];

        foreach ($result['data'] as $tag_name) {
            $tag_name = sanitize_text_field($tag_name);
            if (empty($tag_name)) {
                continue;
            }

            $term = get_term_by('name', $tag_name, 'post_tag');
            if ($term) {
                $tag_ids[] = (int) $term->term_id;
                $tag_names[] = $term->name;
            } else {
                // Create new tag
                $new_term = wp_insert_term($tag_name, 'post_tag');
                if (!is_wp_error($new_term)) {
                    $tag_ids[] = (int) $new_term['term_id'];
                    $tag_names[] = $tag_name;
                }
            }
        }

        // Auto-assign to post if post_id provided
        if ($post_id > 0 && !empty($tag_ids)) {
            wp_set_post_tags($post_id, $tag_ids, true);
        }

        wp_send_json_success([
            'tags'    => $tag_names,
            'tag_ids' => $tag_ids,
        ]);
    }

    public function handle_suggest_categories(): void
    {
        $this->verify_request();

        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        if (empty($content)) {
            wp_send_json_error(['message' => __('No content provided.', 'generatetext')]);
        }

        // Get available categories
        $categories = get_categories(['hide_empty' => false]);
        $cat_map = [];
        foreach ($categories as $cat) {
            $cat_map[mb_strtolower($cat->name)] = [
                'id'   => $cat->term_id,
                'name' => $cat->name,
            ];
        }

        $cat_names = array_column($categories, 'name');

        $api = new GenerateText_API();
        $result = $api->suggest_categories($content, $cat_names);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['error']]);
        }

        // Match suggested categories with existing ones
        $matched = [];
        foreach ($result['data'] as $suggested) {
            $key = mb_strtolower(trim($suggested));
            if (isset($cat_map[$key])) {
                $matched[] = $cat_map[$key];
            }
        }

        if (empty($matched)) {
            wp_send_json_error(['message' => __('No matching categories found.', 'generatetext')]);
        }

        wp_send_json_success(['categories' => $matched]);
    }

    public function handle_generate_title(): void
    {
        $this->verify_request();

        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        if (empty($content)) {
            wp_send_json_error(['message' => __('No content provided.', 'generatetext')]);
        }

        $api = new GenerateText_API();
        $result = $api->generate_title($content);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['error']]);
        }

        wp_send_json_success(['title' => sanitize_text_field($result['data'])]);
    }

    public function handle_rewrite_text(): void
    {
        $this->verify_request();

        $text = wp_kses_post(wp_unslash($_POST['text'] ?? ''));
        if (empty($text)) {
            wp_send_json_error(['message' => __('No text provided.', 'generatetext')]);
        }

        $api = new GenerateText_API();
        $result = $api->rewrite_text($text);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['error']]);
        }

        wp_send_json_success(['rewritten' => wp_kses_post($result['data'])]);
    }
}
