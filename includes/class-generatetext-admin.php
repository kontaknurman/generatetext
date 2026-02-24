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
            fn() => printf('<p>%s</p>', esc_html__('Configure your AI API connection. Choose a provider and enter the API key.', 'generatetext')),
            'generatetext'
        );

        add_settings_field('api_provider', __('API Provider', 'generatetext'), [$this, 'render_provider_field'], 'generatetext', 'generatetext_api');
        add_settings_field('api_key', __('Claude API Key', 'generatetext'), [$this, 'render_api_key_field'], 'generatetext', 'generatetext_api');
        add_settings_field('model', __('Claude Model', 'generatetext'), [$this, 'render_model_field'], 'generatetext', 'generatetext_api');
        add_settings_field('openai_api_key', __('OpenAI API Key', 'generatetext'), [$this, 'render_openai_api_key_field'], 'generatetext', 'generatetext_api');
        add_settings_field('openai_model', __('OpenAI Model', 'generatetext'), [$this, 'render_openai_model_field'], 'generatetext', 'generatetext_api');
        add_settings_field('kimi_api_key', __('Kimi API Key', 'generatetext'), [$this, 'render_kimi_api_key_field'], 'generatetext', 'generatetext_api');
        add_settings_field('kimi_model', __('Kimi Model', 'generatetext'), [$this, 'render_kimi_model_field'], 'generatetext', 'generatetext_api');
        add_settings_field('max_tokens', __('Max Tokens', 'generatetext'), [$this, 'render_max_tokens_field'], 'generatetext', 'generatetext_api');
        add_settings_field('post_types', __('Post Types', 'generatetext'), [$this, 'render_post_types_field'], 'generatetext', 'generatetext_api');

        // Features Section
        add_settings_section(
            'generatetext_features',
            __('Feature Toggles', 'generatetext'),
            fn() => printf('<p>%s</p>', esc_html__('Enable or disable individual features.', 'generatetext')),
            'generatetext'
        );

        add_settings_field('feature_tags', __('Generate Tags', 'generatetext'), [$this, 'render_feature_tags_field'], 'generatetext', 'generatetext_features');
        add_settings_field('feature_category', __('Suggest Category', 'generatetext'), [$this, 'render_feature_category_field'], 'generatetext', 'generatetext_features');
        add_settings_field('feature_title', __('Generate Title', 'generatetext'), [$this, 'render_feature_title_field'], 'generatetext', 'generatetext_features');
        add_settings_field('feature_rewrite', __('Inline Rewrite', 'generatetext'), [$this, 'render_feature_rewrite_field'], 'generatetext', 'generatetext_features');

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
        $post_types = [];
        if (!empty($input['post_types']) && is_array($input['post_types'])) {
            $post_types = array_map('sanitize_text_field', $input['post_types']);
        }

        $valid_providers = ['claude', 'openai', 'kimi'];
        $provider = sanitize_text_field($input['api_provider'] ?? 'claude');
        if (!in_array($provider, $valid_providers, true)) {
            $provider = 'claude';
        }

        return [
            'api_provider'    => $provider,
            'api_key'         => sanitize_text_field($input['api_key'] ?? ''),
            'model'           => sanitize_text_field($input['model'] ?? 'claude-sonnet-4-6'),
            'openai_api_key'  => sanitize_text_field($input['openai_api_key'] ?? ''),
            'openai_model'    => sanitize_text_field($input['openai_model'] ?? 'gpt-5.2'),
            'kimi_api_key'    => sanitize_text_field($input['kimi_api_key'] ?? ''),
            'kimi_model'      => sanitize_text_field($input['kimi_model'] ?? 'kimi-k2.5'),
            'max_tokens'      => min(max(absint($input['max_tokens'] ?? 1024), 256), 4096),
            'post_types'      => $post_types,
            'feature_tags'    => !empty($input['feature_tags']) ? '1' : '0',
            'feature_category'=> !empty($input['feature_category']) ? '1' : '0',
            'feature_title'   => !empty($input['feature_title']) ? '1' : '0',
            'feature_rewrite' => !empty($input['feature_rewrite']) ? '1' : '0',
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

    // ---- Provider field ----

    public function render_provider_field(): void
    {
        $value = $this->get_setting('api_provider', 'claude');
        $providers = [
            'claude' => 'Claude (Anthropic)',
            'openai' => 'OpenAI',
            'kimi'   => 'Kimi (Moonshot AI)',
        ];

        echo '<select name="generatetext_settings[api_provider]" id="gt-api-provider">';
        foreach ($providers as $id => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($id),
                selected($value, $id, false),
                esc_html($label)
            );
        }
        echo '</select>';
        printf('<p class="description">%s</p>', esc_html__('Select which AI provider to use for all features.', 'generatetext'));
    }

    // ---- Claude fields ----

    public function render_api_key_field(): void
    {
        $value = $this->get_setting('api_key');
        printf(
            '<input type="password" name="generatetext_settings[api_key]" value="%s" class="regular-text" autocomplete="off" />
            <p class="description">%s</p>',
            esc_attr($value),
            esc_html__('Your Anthropic API key from console.anthropic.com', 'generatetext')
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

    // ---- OpenAI fields ----

    public function render_openai_api_key_field(): void
    {
        $value = $this->get_setting('openai_api_key');
        printf(
            '<input type="password" name="generatetext_settings[openai_api_key]" value="%s" class="regular-text" autocomplete="off" />
            <p class="description">%s</p>',
            esc_attr($value),
            esc_html__('Your OpenAI API key from platform.openai.com', 'generatetext')
        );
    }

    public function render_openai_model_field(): void
    {
        $value = $this->get_setting('openai_model', 'gpt-4o');
        $models = [
            'gpt-5.2'     => 'GPT-5.2 (Flagship)',
            'gpt-5-mini'  => 'GPT-5 Mini (Fast & affordable)',
            'gpt-4o'      => 'GPT-4o (Previous gen)',
            'gpt-4o-mini' => 'GPT-4o Mini (Budget)',
        ];

        echo '<select name="generatetext_settings[openai_model]">';
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

    // ---- Kimi fields ----

    public function render_kimi_api_key_field(): void
    {
        $value = $this->get_setting('kimi_api_key');
        printf(
            '<input type="password" name="generatetext_settings[kimi_api_key]" value="%s" class="regular-text" autocomplete="off" />
            <p class="description">%s</p>',
            esc_attr($value),
            esc_html__('Your Kimi API key from platform.moonshot.cn', 'generatetext')
        );
    }

    public function render_kimi_model_field(): void
    {
        $value = $this->get_setting('kimi_model', 'kimi-k2.5');
        $models = [
            'kimi-k2.5'        => 'Kimi K2.5 (Latest, multimodal)',
            'kimi-k2'          => 'Kimi K2 (1T MoE, agentic)',
            'moonshot-v1-128k' => 'Moonshot v1 128K (Legacy)',
            'moonshot-v1-8k'   => 'Moonshot v1 8K (Legacy, fast)',
        ];

        echo '<select name="generatetext_settings[kimi_model]">';
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

    // ---- Common fields ----

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

    public function render_post_types_field(): void
    {
        $settings = get_option('generatetext_settings', []);
        $selected = $settings['post_types'] ?? ['post'];
        $post_types = get_post_types(['public' => true], 'objects');

        foreach ($post_types as $pt) {
            if ($pt->name === 'attachment') {
                continue;
            }
            printf(
                '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="generatetext_settings[post_types][]" value="%s" %s /> %s</label>',
                esc_attr($pt->name),
                checked(in_array($pt->name, $selected, true), true, false),
                esc_html($pt->label)
            );
        }
        printf('<p class="description">%s</p>', esc_html__('Select which post types will show GenerateText features.', 'generatetext'));
    }

    // ---- Feature toggle fields ----

    public function render_feature_tags_field(): void
    {
        $this->render_toggle('feature_tags', __('Enable AI tag generation', 'generatetext'));
    }

    public function render_feature_category_field(): void
    {
        $this->render_toggle('feature_category', __('Enable AI category suggestion', 'generatetext'));
    }

    public function render_feature_title_field(): void
    {
        $this->render_toggle('feature_title', __('Enable AI title generation', 'generatetext'));
    }

    public function render_feature_rewrite_field(): void
    {
        $this->render_toggle('feature_rewrite', __('Enable inline text rewrite in editor toolbar', 'generatetext'));
    }

    private function render_toggle(string $key, string $label): void
    {
        $value = $this->get_setting($key, '1');
        printf(
            '<label><input type="checkbox" name="generatetext_settings[%s]" value="1" %s /> %s</label>',
            esc_attr($key),
            checked($value, '1', false),
            esc_html($label)
        );
    }

    // ---- Prompt fields ----

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

    // ---- Settings page ----

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

        <script>
        (function() {
            var providerSelect = document.getElementById('gt-api-provider');
            if (!providerSelect) return;

            function toggleProviderFields() {
                var provider = providerSelect.value;
                var rows = document.querySelectorAll('.form-table tr');
                rows.forEach(function(row) {
                    var label = row.querySelector('th');
                    if (!label) return;
                    var text = label.textContent.trim();

                    // Claude fields
                    if (text === '<?php echo esc_js(__('Claude API Key', 'generatetext')); ?>' ||
                        text === '<?php echo esc_js(__('Claude Model', 'generatetext')); ?>') {
                        row.style.display = (provider === 'claude') ? '' : 'none';
                    }
                    // OpenAI fields
                    if (text === '<?php echo esc_js(__('OpenAI API Key', 'generatetext')); ?>' ||
                        text === '<?php echo esc_js(__('OpenAI Model', 'generatetext')); ?>') {
                        row.style.display = (provider === 'openai') ? '' : 'none';
                    }
                    // Kimi fields
                    if (text === '<?php echo esc_js(__('Kimi API Key', 'generatetext')); ?>' ||
                        text === '<?php echo esc_js(__('Kimi Model', 'generatetext')); ?>') {
                        row.style.display = (provider === 'kimi') ? '' : 'none';
                    }
                });
            }

            providerSelect.addEventListener('change', toggleProviderFields);
            toggleProviderFields();
        })();
        </script>
        <?php
    }
}
