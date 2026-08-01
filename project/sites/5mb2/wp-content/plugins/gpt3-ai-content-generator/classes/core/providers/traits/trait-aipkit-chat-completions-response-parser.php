<?php

namespace WPAICG\Core\Providers\Traits; // *** Correct namespace ***

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Trait for parsing responses from OpenAI Chat Completions compatible APIs.
 * Used by OpenAI (v1/chat/completions), Azure, and OpenRouter strategies.
 * NOTE: The OpenAI strategy uses the Responses API (v1/responses) which has a different structure,
 * so it will override this trait's parse_chat_response method.
 */
trait ChatCompletionsResponseParserTrait {

    /**
     * Parses a standard Chat Completions API response.
     *
     * @param array $decoded_response The decoded JSON response.
     * @param array $request_data     The original request data (unused here but part of interface).
     * @return array|WP_Error ['content' => string, 'usage' => array|null] or WP_Error.
     */
    public function parse_chat_response(array $decoded_response, array $request_data) {
        $content = null;
        $choice = (isset($decoded_response['choices'][0]) && is_array($decoded_response['choices'][0]))
            ? $decoded_response['choices'][0]
            : [];
        $message = (isset($choice['message']) && is_array($choice['message']))
            ? $choice['message']
            : [];
        $has_content_field = array_key_exists('content', $message);

        // Standard Chat Completions structure
        if ($has_content_field) {
            $content = $message['content'] === null ? '' : trim((string) $message['content']);
        } elseif (isset($choice['delta']) && is_array($choice['delta']) && array_key_exists('content', $choice['delta']) && $choice['delta']['content'] !== null) { // Handle potential stream-like response structure
            $content = trim((string) $choice['delta']['content']);
        } elseif (array_key_exists('text', $choice) && $choice['text'] !== null) { // Handle older text field if present
             $content = trim((string) $choice['text']);
        }

        if ($content === null || $content === '') {
             // Check for specific errors like content filters before declaring invalid structure
             if (isset($choice['finish_reason']) && $choice['finish_reason'] === 'content_filter') {
                 return new WP_Error('content_filter', __('Response blocked due to content filtering.', 'gpt3-ai-content-generator'));
             }

            $finish_reason = isset($choice['finish_reason']) ? (string) $choice['finish_reason'] : '';
            $error_data = [
                'finish_reason' => $finish_reason,
            ];
            if (isset($decoded_response['usage']) && is_array($decoded_response['usage'])) {
                $error_data['usage'] = $decoded_response['usage'];
            }

            if ($content === '' || in_array($finish_reason, ['length', 'insufficient_system_resource'], true)) {
                return new WP_Error(
                    'empty_response_chatcompletion',
                    $this->get_empty_chat_completion_message($finish_reason, $message, $decoded_response, $request_data),
                    $error_data
                );
            }

            return new WP_Error(
                'invalid_response_structure_chatcompletion',
                __('Unexpected response structure from Chat Completions API.', 'gpt3-ai-content-generator'),
                $error_data
            );
        }

        // Extract usage (standard Chat Completion format)
        $usage = null;
        if (isset($decoded_response['usage']) && is_array($decoded_response['usage'])) {
            $usage = [
                'input_tokens'  => $decoded_response['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $decoded_response['usage']['completion_tokens'] ?? 0,
                'total_tokens'  => $decoded_response['usage']['total_tokens'] ?? 0,
                'provider_raw' => $decoded_response['usage'], // Include raw provider usage
            ];
        }

        return ['content' => $content, 'usage' => $usage];
    }

    private function get_empty_chat_completion_message(string $finish_reason, array $message, array $decoded_response, array $request_data): string {
        $has_reasoning_output = !empty($message['reasoning_content']);
        $reasoning_tokens = $decoded_response['usage']['completion_tokens_details']['reasoning_tokens'] ?? 0;
        if (is_numeric($reasoning_tokens) && (int) $reasoning_tokens > 0) {
            $has_reasoning_output = true;
        }

        $model = strtolower((string) ($request_data['model'] ?? ''));
        $provider_label = (strpos($model, 'deepseek') !== false || $has_reasoning_output)
            ? 'DeepSeek'
            : __('The AI provider', 'gpt3-ai-content-generator');

        if ($finish_reason === 'length') {
            if ($has_reasoning_output) {
                return sprintf(
                    /* translators: %s: AI provider name. */
                    __('%s returned no final content because reasoning consumed the output budget. Disable thinking/reasoning for this task or increase max tokens.', 'gpt3-ai-content-generator'),
                    $provider_label
                );
            }

            return __('The AI provider returned no final content before reaching the max token limit. Increase max tokens or reduce the prompt size.', 'gpt3-ai-content-generator');
        }

        if ($finish_reason === 'insufficient_system_resource') {
            return sprintf(
                /* translators: %s: AI provider name. */
                __('%s could not produce a final answer because the provider reported insufficient system resources. Please retry, or switch models if it continues.', 'gpt3-ai-content-generator'),
                $provider_label
            );
        }

        return __('The AI provider returned an empty final response. Please retry, increase max tokens, or disable thinking/reasoning if the selected model supports it.', 'gpt3-ai-content-generator');
    }
}
