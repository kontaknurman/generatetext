<?php

defined('ABSPATH') || exit;

class GenerateText_Admin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu_page(): void
    {
        add_options_page(
            __('GenerateText Settings', 'generatetext'),
            __('GenerateText', 'generatetext'),
            'manage_options',
            'generatetext',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        register_setting('generatetext_settings_group', 'generatetext_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);

        // API Section
        add_settings_section(
            'generatetext_api',
            __('API Configuration', 'generatetext'),
            fn() => printf('<p>%s</p>', esc_html__('Configure your Claude API connection.', 'generatetext')),
            'generatetext'
        );

        add_settings_field('api_key', __('API Key', 'generatetext'), [$this, 'render_api_key_field'], 'generatetext', 'generatetext_api');
        add_settings_field('model', __('Model', 'generatetext'), [$this, 'render_model_field'], 'generatetext', 'generatetext_api');
        add_settings_field('max_tokens', __('Max Tokens', 'generatetext'), [$this, 'render_max_tokens_field'], 'generatetext', 'generatetext_api');

        // Prompts Section
        add_settings_section(
            'generatetext_prompts',
            __('Prompt Templates', 'generatetext'),
            fn() => printf('<p>%s</p>', esc_html__('Customize prompts for each feature. Use placeholders: {content}, {existing_tags}, {categories}, {text}.', 'generatetext')),
            'generatetext'
        );

        add_settings_field('prompt_tags', __('Generate Tags', 'generatetext'), [$this, 'render_prompt_tags_field'], 'generatetext', 'generatetext_prompts');
        add_settings_field('prompt_category', __('Suggest Category', 'generatetext'), [$this, 'render_prompt_category_field'], 'generatetext', 'generatetext_prompts');
        add_settings_field('prompt_title', __('Generate Title', 'generatetext'), [$this, 'render_prompt_title_field'], 'generatetext', 'generatetext_prompts');
        add_settings_field('prompt_rewrite', __('Rewrite Text', 'generatetext'), [$this, 'render_prompt_rewrite_field'], 'generatetext', 'generatetext_prompts');
    }

    public function sanitize_settings(array $input): array
    {
        return [
            'api_key'         => sanitize_text_field($input['api_key'] ?? ''),
            'model'           => sanitize_text_field($input['model'] ?? 'claude-sonnet-4-6'),
            'max_tokens'      => min(max(absint($input['max_tokens'] ?? 1024), 256), 4096),
            'prompt_tags'     => sanitize_textarea_field($input['prompt_tags'] ?? ''),
            'prompt_category' => sanitize_textarea_field($input['prompt_category'] ?? ''),
            'prompt_title'    => sanitize_textarea_field($input['prompt_title'] ?? ''),
            'prompt_rewrite'  => sanitize_textarea_field($input['prompt_rewrite'] ?? ''),
        ];
    }

    private function get_setting(string $key, string $default = ''): string
    {
        $settings = get_option('generatetext_settings', []);
        return $settings[$key] ?? $default;
    }

    public function render_api_key_field(): void
    {
        $value = $this->get_setting('api_key');
        printf(
            '<input type="password" name="generatetext_settings[api_key]" value="%s" class="regular-text" autocomplete="off" />
            <p class="description">%s</p>',
            esc_attr($value),
            esc_html__('Your Anthropic API key. Get one at console.anthropic.com.', 'generatetext')
        );
    }

    public function render_model_field(): void
    {
        $value = $this->get_setting('model', 'claude-sonnet-4-6');
        $models = [
            'claude-opus-4-6'   => 'Claude Opus 4.6 (Most capable)',
            'claude-sonnet-4-6' => 'Claude Sonnet 4.6 (Balanced)',
            'claude-haiku-4-5'  => 'Claude Haiku 4.5 (Fast & affordable)',
        ];

        echo '<select name="generatetext_settings[model]">';
        foreach ($models as $id => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($id),
                selected($value, $id, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    public function render_max_tokens_field(): void
    {
        $value = $this->get_setting('max_tokens', '1024');
        printf(
            '<input type="number" name="generatetext_settings[max_tokens]" value="%s" min="256" max="4096" step="256" class="small-text" />
            <p class="description">%s</p>',
            esc_attr($value),
            esc_html__('Maximum tokens for API responses (256-4096).', 'generatetext')
        );
    }

    public function render_prompt_tags_field(): void
    {
        $this->render_textarea('prompt_tags', '{content}, {existing_tags}');
    }

    public function render_prompt_category_field(): void
    {
        $this->render_textarea('prompt_category', '{content}, {categories}');
    }

    public function render_prompt_title_field(): void
    {
        $this->render_textarea('prompt_title', '{content}');
    }

    public function render_prompt_rewrite_field(): void
    {
        $this->render_textarea('prompt_rewrite', '{text}');
    }

    private function render_textarea(string $key, string $placeholders): void
    {
        $value = $this->get_setting($key);
        printf(
            '<textarea name="generatetext_settings[%s]" rows="4" class="large-text">%s</textarea>
            <p class="description">%s: %s</p>',
            esc_attr($key),
            esc_textarea($value),
            esc_html__('Available placeholders', 'generatetext'),
            esc_html($placeholders)
        );
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('generatetext_settings_group');
                do_settings_sections('generatetext');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
