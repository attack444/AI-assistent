<?php

namespace WPAICG\Vector\PostProcessor\Chroma;

use WPAICG\Core\AIPKit_AI_Caller;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles embedding generation for Chroma post processing.
 */
class ChromaEmbeddingHandler
{
    private $ai_caller;

    public function __construct()
    {
        if (!class_exists(\WPAICG\Core\AIPKit_AI_Caller::class)) {
            $ai_caller_path = WPAICG_PLUGIN_DIR . 'classes/core/class-aipkit_ai_caller.php';
            if (file_exists($ai_caller_path)) {
                require_once $ai_caller_path;
            }
        }
        if (class_exists(\WPAICG\Core\AIPKit_AI_Caller::class)) {
            $this->ai_caller = new AIPKit_AI_Caller();
        }
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function generate_embedding(string $content_string, string $embedding_provider, string $embedding_model)
    {
        $embedding_result = $this->generate_embeddings([$content_string], $embedding_provider, $embedding_model);
        if (is_wp_error($embedding_result)) {
            return $embedding_result;
        }

        return $embedding_result;
    }

    /**
     * @param array<int,string> $content_strings
     * @return mixed[]|\WP_Error
     */
    public function generate_embeddings(array $content_strings, string $embedding_provider, string $embedding_model)
    {
        if (!$this->ai_caller) {
            return new WP_Error('ai_caller_missing_chroma_embed', 'AI Caller component is not available for Chroma embeddings.');
        }

        $content_strings = array_values($content_strings);
        if (empty($content_strings)) {
            return new WP_Error('embedding_failed_chroma_embed', 'No content provided for Chroma embeddings.');
        }

        $embedding_options = ['model' => $embedding_model];
        $embedding_result = $this->ai_caller->generate_embeddings($embedding_provider, $content_strings, $embedding_options);
        if (!is_wp_error($embedding_result) && isset($embedding_result['embeddings']) && is_array($embedding_result['embeddings']) && count($embedding_result['embeddings']) === count($content_strings)) {
            return $embedding_result;
        }

        if (count($content_strings) === 1) {
            return is_wp_error($embedding_result) ? $embedding_result : new WP_Error('embedding_failed_chroma_embed', 'No embeddings returned for Chroma.');
        }

        $embeddings = [];
        foreach ($content_strings as $content_string) {
            $single_result = $this->ai_caller->generate_embeddings($embedding_provider, $content_string, $embedding_options);
            if (is_wp_error($single_result)) {
                return $single_result;
            }
            if (empty($single_result['embeddings'][0]) || !is_array($single_result['embeddings'][0])) {
                return new WP_Error('embedding_failed_chroma_embed', 'No embeddings returned for Chroma.');
            }
            $embeddings[] = $single_result['embeddings'][0];
        }

        return ['embeddings' => $embeddings, 'usage' => null];
    }
}
