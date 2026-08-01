<?php

namespace WPAICG\Core\Providers\Traits; // *** Correct namespace ***

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Trait for parsing Server-Sent Events (SSE) from OpenAI Chat Completions compatible APIs.
 * Used by Azure and OpenRouter strategies.
 * NOTE: The OpenAI strategy uses the Responses API (v1/responses) which has a different SSE structure.
 */
trait ChatCompletionsSSEParserTrait {

    /**
     * Parses an SSE chunk from a Chat Completions compatible stream.
     *
     * @param string $sse_chunk       The raw chunk received.
     * @param string &$current_buffer Reference to the incomplete buffer.
     * @return array Result containing delta, usage, flags.
     */
    public function parse_sse_chunk(string $sse_chunk, string &$current_buffer): array {
        $current_buffer .= $sse_chunk;
        $result = ['delta' => null, 'usage' => null, 'is_error' => false, 'is_warning' => false, 'is_done' => false];

        while (($line_end_pos = strpos($current_buffer, "\n\n")) !== false) {
            $event_block = (string) substr($current_buffer, 0, $line_end_pos);
            $current_buffer = (string) substr($current_buffer, $line_end_pos + 2); // Move buffer past the block

            $event_data_json = null;
            $event_type = 'message'; // Default

            foreach (explode("\n", $event_block) as $line) {
                $line = rtrim($line, "\r");
                if (empty($line) || strpos($line, ':') === false) continue;
                [$field, $value] = explode(':', $line, 2);
                $field = trim($field);
                $value = trim($value);
                if ($field === 'data') {
                    $event_data_json = $value;
                    break; // Found data line for this block
                }
            }

            if ($event_data_json === '[DONE]') {
                $result['is_done'] = true;
                continue; // Process next block if any
            }

            if ($event_data_json) {
                $decoded = json_decode($event_data_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Check for API error structure within the data payload
                    if (isset($decoded['error'])) {
                        $result['delta'] = $this->parse_error_response($decoded, 500); // Use the strategy's error parser
                        $result['is_error'] = true;
                        return $result; // Fatal error, stop processing
                    }

                    // Check for usage data (often comes at the end in stream_options)
                    if (isset($decoded['usage']) && is_array($decoded['usage'])) {
                        $result['usage'] = [
                            'input_tokens'  => $decoded['usage']['prompt_tokens'] ?? 0,
                            'output_tokens' => $decoded['usage']['completion_tokens'] ?? 0,
                            'total_tokens'  => $decoded['usage']['total_tokens'] ?? 0,
                            'provider_raw' => $decoded['usage'],
                        ];
                        // Usage might signal the end if no content delta is present
                        if (!isset($decoded['choices'][0]['delta']['content'])) {
                             // Don't mark as done here, wait for [DONE] or final chunk processing
                             // $result['is_done'] = true;
                        }
                    }

                    // Check for content delta
                    if (isset($decoded['choices'][0]['delta']['content'])) {
                        $delta_text = $decoded['choices'][0]['delta']['content'];
                        if ($result['delta'] === null) { $result['delta'] = ''; }
                        $result['delta'] .= $delta_text;
                    }

                    // Check for finish reason which might indicate content filtering or other warnings
                    if (isset($decoded['choices'][0]['finish_reason'])) {
                        $finish_reason = $decoded['choices'][0]['finish_reason'];
                        if ($finish_reason === 'content_filter') {
                            if ($result['delta'] === null) { $result['delta'] = ''; }
                            $result['delta'] .= sprintf(' (%s)', __('Warning: Content Filtered', 'gpt3-ai-content-generator'));
                            $result['is_warning'] = true;
                        } elseif ($finish_reason !== null && $finish_reason !== 'stop') {
                             // Other reasons like length, tool_calls etc. - treat as potential end signal for parsing but not necessarily fatal error
                             // We might not need to mark 'is_done' here, let [DONE] handle it.
                        }
                    }
                }
            }
        } // End while loop processing blocks

        return $result;
    }
}