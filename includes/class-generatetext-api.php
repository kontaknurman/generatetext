<?php

defined('ABSPATH') || exit;

class GenerateText_API
{
    private const PROVIDERS = [
        'claude' => [
            'url'     => 'https://api.anthropic.com/v1/messages',
            'timeout' => 60,
        ],
        'openai' => [
            'url'     => 'https://api.openai.com/v1/chat/completions',
            'timeout' => 60,
        ],
        'kimi' => [
            'url'     => 'https://api.moonshot.ai/v1/chat/completions',
            'timeout' => 60,
        ],
    ];

    private string $provider;
    private string $api_key;
    private string $model;
    private int $max_tokens;

    public function __construct()
    {
        $settings        = get_option('generatetext_settings', []);
        $this->provider  = sanitize_text_field($settings['api_provider'] ?? 'claude');
        $this->max_tokens = absint($settings['max_tokens'] ?? 1024);

        // Load provider-specific API key and model
        match ($this->provider) {
            'openai' => [
                $this->api_key = sanitize_text_field($settings['openai_api_key'] ?? ''),
                $this->model   = sanitize_text_field($settings['openai_model'] ?? 'gpt-5.2'),
            ],
            'kimi' => [
                $this->api_key = sanitize_text_field($settings['kimi_api_key'] ?? ''),
                $this->model   = sanitize_text_field($settings['kimi_model'] ?? 'kimi-k2.5'),
            ],
            default => [
                $this->api_key = sanitize_text_field($settings['api_key'] ?? ''),
                $this->model   = sanitize_text_field($settings['model'] ?? 'claude-sonnet-4-6'),
            ],
        };
    }

    public function is_configured(): bool
    {
        return !empty($this->api_key);
    }

    /**
     * Send a message to the configured AI provider.
     *
     * @return array{success: bool, data?: string, error?: string}
     */
    public function send_message(string $system_prompt, string $user_message): array
    {
        if (!$this->is_configured()) {
            return ['success' => false, 'error' => __('API key not configured.', 'generatetext')];
        }

        if (!isset(self::PROVIDERS[$this->provider])) {
            return ['success' => false, 'error' => __('Invalid API provider.', 'generatetext')];
        }

        return match ($this->provider) {
            'openai', 'kimi' => $this->send_openai_compatible($system_prompt, $user_message),
            default          => $this->send_claude($system_prompt, $user_message),
        };
    }

    /**
     * Send message via Claude (Anthropic) API.
     */
    private function send_claude(string $system_prompt, string $user_message): array
    {
        $body = [
            'model'      => $this->model,
            'max_tokens' => $this->max_tokens,
            'messages'   => [
                ['role' => 'user', 'content' => $user_message],
            ],
        ];

        if (!empty($system_prompt)) {
            $body['system'] = $system_prompt;
        }

        $response = wp_remote_post(self::PROVIDERS['claude']['url'], [
            'timeout' => self::PROVIDERS['claude']['timeout'],
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $error_msg = $body['error']['message'] ?? __('Unknown API error.', 'generatetext');
            return ['success' => false, 'error' => sprintf('[%d] %s', $code, $error_msg)];
        }

        $text = $body['content'][0]['text'] ?? '';
        return ['success' => true, 'data' => $text];
    }

    /**
     * Send message via OpenAI-compatible API (OpenAI, Kimi/Moonshot).
     */
    private function send_openai_compatible(string $system_prompt, string $user_message): array
    {
        $messages = [];
        if (!empty($system_prompt)) {
            $messages[] = ['role' => 'system', 'content' => $system_prompt];
        }
        $messages[] = ['role' => 'user', 'content' => $user_message];

        $body = [
            'model'      => $this->model,
            'max_tokens' => $this->max_tokens,
            'messages'   => $messages,
        ];

        $provider_config = self::PROVIDERS[$this->provider];

        $response = wp_remote_post($provider_config['url'], [
            'timeout' => $provider_config['timeout'],
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $error_msg = $body['error']['message'] ?? __('Unknown API error.', 'generatetext');
            return ['success' => false, 'error' => sprintf('[%d] %s', $code, $error_msg)];
        }

        $text = $body['choices'][0]['message']['content'] ?? '';
        return ['success' => true, 'data' => $text];
    }

    public function generate_tags(string $content, array $existing_tags): array
    {
        $settings = get_option('generatetext_settings', []);
        $prompt_template = $settings['prompt_tags'] ?? '';

        $tags_list = !empty($existing_tags) ? implode(', ', $existing_tags) : 'none';
        $prompt = str_replace(
            ['{existing_tags}', '{content}'],
            [$tags_list, $this->truncate_content($content)],
            $prompt_template
        );

        $result = $this->send_message(
            'You are a helpful assistant that generates relevant tags for blog posts. Always respond with valid JSON.',
            $prompt
        );

        if (!$result['success']) {
            return $result;
        }

        $tags = $this->parse_json_array($result['data']);
        if ($tags === null) {
            return ['success' => false, 'error' => __('Failed to parse tags from API response.', 'generatetext')];
        }

        return ['success' => true, 'data' => $tags];
    }

    public function suggest_categories(string $content, array $available_categories): array
    {
        $settings = get_option('generatetext_settings', []);
        $prompt_template = $settings['prompt_category'] ?? '';

        $cat_list = implode(', ', $available_categories);
        $prompt = str_replace(
            ['{categories}', '{content}'],
            [$cat_list, $this->truncate_content($content)],
            $prompt_template
        );

        $result = $this->send_message(
            'You are a helpful assistant that categorizes blog posts. Only select from the provided categories. Always respond with valid JSON.',
            $prompt
        );

        if (!$result['success']) {
            return $result;
        }

        $categories = $this->parse_json_array($result['data']);
        if ($categories === null) {
            return ['success' => false, 'error' => __('Failed to parse categories from API response.', 'generatetext')];
        }

        return ['success' => true, 'data' => $categories];
    }

    public function generate_title(string $content): array
    {
        $settings = get_option('generatetext_settings', []);
        $prompt_template = $settings['prompt_title'] ?? '';

        $prompt = str_replace('{content}', $this->truncate_content($content), $prompt_template);

        $result = $this->send_message(
            'You are a helpful assistant that generates compelling blog post titles.',
            $prompt
        );

        if (!$result['success']) {
            return $result;
        }

        return ['success' => true, 'data' => trim($result['data'], " \t\n\r\0\x0B\"'")];
    }

    public function rewrite_text(string $text): array
    {
        $settings = get_option('generatetext_settings', []);
        $prompt_template = $settings['prompt_rewrite'] ?? '';

        $prompt = str_replace('{text}', $text, $prompt_template);

        $result = $this->send_message(
            'You are a professional content editor. Rewrite the given text while maintaining the same meaning.',
            $prompt
        );

        if (!$result['success']) {
            return $result;
        }

        return ['success' => true, 'data' => trim($result['data'])];
    }

    private function truncate_content(string $content, int $max_chars = 8000): string
    {
        $content = wp_strip_all_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);

        if (mb_strlen($content) > $max_chars) {
            $content = mb_substr($content, 0, $max_chars) . '...';
        }

        return trim($content);
    }

    private function parse_json_array(string $text): ?array
    {
        // Try direct parse first
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return array_values(array_filter($decoded, 'is_string'));
        }

        // Try to extract JSON array from text
        if (preg_match('/\[([^\]]+)\]/', $text, $matches)) {
            $decoded = json_decode('[' . $matches[1] . ']', true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, 'is_string'));
            }
        }

        return null;
    }
}
