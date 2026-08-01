<?php

namespace WPAICG\Core\Providers\Traits; // *** Correct namespace ***

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Trait for formatting payloads compatible with OpenAI Chat Completions API.
 * Used by OpenAI, Azure, and OpenRouter strategies.
 */
trait ChatCompletionsPayloadTrait {

    /**
     * Formats the payload for chat completions.
     *
     * @param string $instructions System instructions.
     * @param array  $history      Conversation history [{role: 'user'|'assistant', content: '...'}].
     * @param string $user_message The latest user message.
     * @param array  $ai_params    AI parameters (temperature, max_tokens, etc.).
     * @param string $model        Model name (required by some providers in payload).
     * @param bool   $include_model Whether to include the 'model' key in the payload.
     * @return array The formatted payload.
     */
    protected function format_chat_completions_payload(
        string $instructions,
        array $history,
        string $user_message,
        array $ai_params,
        string $model,
        bool $include_model = false
    ): array {
        $messages = [];
        if (!empty($instructions)) {
            $messages[] = ['role' => 'system', 'content' => $instructions];
        }
        foreach ($history as $msg) {
            $role = ($msg['role'] === 'bot') ? 'assistant' : $msg['role'];
            $content = isset($msg['content']) ? trim($msg['content']) : '';
            if ($content !== '' && in_array($role, ['system', 'user', 'assistant'])) {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }
        // Add the latest user message if provided
        if (!empty($user_message)) {
            $messages[] = ['role' => 'user', 'content' => $user_message];
        }

        $body_data = ['messages' => $messages];

        if ($include_model) {
            $body_data['model'] = $model;
        }

        // Map AIPKit standard AI params to Chat Completions API params
        $param_map = [
            'temperature' => 'temperature',
            'max_completion_tokens' => 'max_tokens', // API uses 'max_tokens'
            'top_p' => 'top_p', 
            'stop' => 'stop',
        ];

        foreach ($param_map as $aipkit_key => $api_key) {
            if (isset($ai_params[$aipkit_key])) {
                $value = $ai_params[$aipkit_key];
                if (in_array($api_key, ['temperature', 'top_p'])) {
                    $body_data[$api_key] = floatval($value);
                } elseif ($api_key === 'max_tokens') {
                    $body_data[$api_key] = absint($value);
                } elseif ($api_key === 'stop' && !empty($value)) {
                    // Ensure 'stop' is an array or null
                    $body_data[$api_key] = is_string($value) ? [$value] : (is_array($value) ? $value : null);
                    if (empty($body_data[$api_key])) unset($body_data[$api_key]);
                }
            }
        }

        // Remove 'max_completion_tokens' if it exists from the direct mapping
        unset($body_data['max_completion_tokens']);

        return $body_data;
    }

     /**
     * Formats the payload for SSE chat completions.
     *
     * @param array  $messages     Formatted messages array.
     * @param string $instructions System instructions.
     * @param array  $ai_params    AI parameters.
     * @param string $model        Model name.
     * @param bool   $include_model Whether to include the 'model' key in the payload.
     * @param bool   $request_usage Whether to request usage data in the stream.
     * @return array The formatted SSE payload.
     */
    protected function format_sse_chat_completions_payload(
        array $messages,
        string $instructions,
        array $ai_params,
        string $model,
        bool $include_model = false,
        bool $request_usage = true
    ): array {
        // Use the base formatter, passing empty user message as it's already in $messages
        $payload = $this->format_chat_completions_payload($instructions, $messages, '', $ai_params, $model, $include_model);

        // Remove the last (empty) user message if it was added by the base formatter
        if (end($payload['messages'])['role'] === 'user' && empty(end($payload['messages'])['content'])) {
            array_pop($payload['messages']);
        }

        // Add stream flag
        $payload['stream'] = true;

        // Add usage request if desired and supported (OpenAI API specific)
        if ($request_usage && $this instanceof \WPAICG\Core\Providers\OpenAIProviderStrategy) { // *** Use correct namespace ***
            // The /v1/responses API doesn't use stream_options, usage is returned by default on completion events.
        } else if ($request_usage && ($this instanceof \WPAICG\Core\Providers\AzureProviderStrategy || $this instanceof \WPAICG\Core\Providers\OpenRouterProviderStrategy)) { // *** Use correct namespace ***
             $payload['stream_options'] = ['include_usage' => true];
        }


        return $payload;
    }
}
