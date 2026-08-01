<?php


namespace WPAICG;

use WPAICG\Core\AIPKit_Models_API;
use WPAICG\Vector\AIPKit_Vector_Store_Registry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AIPKit_Providers
 */
class AIPKit_Providers
{
    private const OPENAI_DEFAULT_IMAGE_MODEL = 'gpt-image-2';
    private const OPENAI_IMAGE_MODELS = [
        ['id' => 'gpt-image-2', 'name' => 'GPT Image 2'],
        ['id' => 'gpt-image-1.5', 'name' => 'GPT Image 1.5'],
        ['id' => 'gpt-image-1', 'name' => 'GPT Image 1'],
        ['id' => 'gpt-image-1-mini', 'name' => 'GPT Image 1 mini'],
    ];
    private const DEEPSEEK_DEPRECATED_MODEL_SUFFIXES = ['chat', 'reasoner'];

    private static $provider_defaults = [
        'OpenAI' => [
            'api_key' => '', 'model' => '', 'embedding_model' => '',
            'base_url' => 'https://api.openai.com', 'api_version' => 'v1',
            'store_conversation' => '0',
            'expiration_policy' => 7, // NEW: Default expiration policy in days
        ],
        'OpenRouter' => [
            'api_key' => '', 'model' => '',
            'base_url' => 'https://openrouter.ai/api', 'api_version' => 'v1',
        ],
        'Google' => [
            'api_key' => '', 'model' => '', 'embedding_model' => '',
            'base_url' => 'https://generativelanguage.googleapis.com', 'api_version' => 'v1beta',
            'safety_settings' => []
        ],
        'Azure' => [
            'api_key' => '', 'model' => '', 'endpoint' => '', 'embeddings' => '',
            'api_version_authoring' => '2023-03-15-preview', 'api_version_inference' => '2025-01-01-preview',
            'api_version_images' => '2025-04-01-preview'
        ],
        'Claude' => [
            'api_key' => '', 'model' => '',
            'base_url' => 'https://api.anthropic.com', 'api_version' => '2023-06-01',
        ],
        'DeepSeek' => [
            'api_key' => '', 'model' => '',
            'base_url' => 'https://api.deepseek.com', 'api_version' => 'v1',
        ],
        'xAI' => [
            'api_key' => '', 'model' => '',
            'base_url' => 'https://api.x.ai', 'api_version' => 'v1',
        ],
        'Ollama' => [
            'model' => '',
            'base_url' => 'http://localhost:11434',
        ],
        'ElevenLabs' => [
            'api_key' => '', 'voice_id' => '', 'model_id' => '',
            'base_url' => 'https://api.elevenlabs.io', 'api_version' => 'v1',
        ],
        'Pexels' => [
            'api_key' => '',
        ],
        'Pixabay' => [
            'api_key' => '',
        ],
        'Pinecone' => [
            'api_key' => '',
        ],
        'Qdrant' => [ // Ensure API key is part of defaults as it's now mandatory for cloud
            'api_key' => '', 'url' => '', 'default_collection' => '',
        ],
        'Chroma' => [
            'api_key' => '',
            'url' => '',
            'tenant' => 'default_tenant',
            'database' => 'default_database',
            'default_collection' => '',
        ],
        'Replicate' => [
            'api_key' => '',
        ],
    ];

    private static $model_catalog = [
        'OpenAI' => [
            'default' => 'gpt-5.4-mini',
            'models' => [
                'gpt-5.6-sol',
                'gpt-5.6-terra',
                'gpt-5.6-luna',
                'gpt-5.4-mini',
                'gpt-5.4',
                'gpt-5.4-nano',
                'gpt-4.1-mini',
                'gpt-4.1',
            ],
        ],
        'OpenAIEmbedding' => [
            'default' => 'text-embedding-3-small',
            'models' => [
                ['id' => 'text-embedding-3-small', 'name' => 'Text Embedding 3 Small (1536)'],
                ['id' => 'text-embedding-3-large', 'name' => 'Text Embedding 3 Large (3072)'],
                ['id' => 'text-embedding-ada-002', 'name' => 'Text Embedding Ada 002 (1536)'],
            ],
        ],
        'OpenRouter' => [
            'default' => 'moonshotai/kimi-k2.5',
            'models' => [
                ['id' => 'moonshotai/kimi-k2.5', 'name' => 'Kimi K2.5'],
                ['id' => 'anthropic/claude-sonnet-4.5', 'name' => 'Claude Sonnet 4.5'],
                ['id' => 'anthropic/claude-opus-4.7', 'name' => 'Claude Opus 4.7'],
                ['id' => 'anthropic/claude-opus-4.6', 'name' => 'Claude Opus 4.6'],
                ['id' => 'google/gemini-3.5-flash', 'name' => 'Gemini 3.5 Flash'],
                ['id' => 'google/gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash'],
                ['id' => 'deepseek/deepseek-v3.2', 'name' => 'DeepSeek V3.2'],
                ['id' => 'openai/gpt-5-nano', 'name' => 'GPT-5 Nano'],
                ['id' => 'x-ai/grok-4.1-fast', 'name' => 'Grok 4.1 Fast'],
                ['id' => 'z-ai/glm-4.7', 'name' => 'GLM 4.7'],
            ],
        ],
        'OpenRouterEmbedding' => [
            'default' => '',
            'models' => [],
        ],
        'Google' => [
            'default' => 'gemini-2.5-flash',
            'models' => [
                ['id' => 'gemini-3.5-flash', 'name' => 'Gemini 3.5 Flash'],
                ['id' => 'gemini-3-flash-preview', 'name' => 'Gemini 3 Flash Preview'],
                ['id' => 'gemini-3-pro-preview', 'name' => 'Gemini 3 Pro Preview'],
                ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash'],
                ['id' => 'gemini-2.5-flash-lite', 'name' => 'Gemini 2.5 Flash Lite'],
            ],
        ],
        'GoogleImage' => [
            'default' => 'gemini-3.1-flash-image-preview',
            'models' => [
                ['id' => 'gemini-3.1-flash-image-preview', 'name' => 'Gemini 3.1 Flash Image Preview (Nano Banana 2)'],
                ['id' => 'gemini-3-pro-image-preview', 'name' => 'Gemini 3 Pro Image Preview (Nano Banana Pro)'],
                ['id' => 'gemini-2.5-flash-image', 'name' => 'Gemini 2.5 Flash Image (Nano Banana)'],
                ['id' => 'imagen-4.0-generate-001', 'name' => 'Imagen 4'],
                ['id' => 'imagen-4.0-fast-generate-001', 'name' => 'Imagen 4 Fast'],
                ['id' => 'imagen-4.0-ultra-generate-001', 'name' => 'Imagen 4 Ultra'],
            ],
        ],
        'xAIImage' => [
            'default' => 'grok-imagine-image-quality',
            'models' => [
                ['id' => 'grok-imagine-image-quality', 'name' => 'Grok Imagine Image Quality'],
                ['id' => 'grok-imagine-image', 'name' => 'Grok Imagine Image'],
            ],
        ],
        'GoogleVideo' => [
            'default' => '',
            'models' => [],
        ],
        'GoogleEmbedding' => [
            'default' => 'gemini-embedding-2-preview',
            'models' => [
                ['id' => 'gemini-embedding-2-preview', 'name' => 'Gemini Embedding 2 Preview (3072)', 'dimensions' => 3072],
                ['id' => 'gemini-embedding-001', 'name' => 'Gemini Embedding 001 (3072)', 'dimensions' => 3072],
                ['id' => 'models/text-embedding-004', 'name' => 'Embedding 004 (768)', 'dimensions' => 768],
            ],
        ],
        'Claude' => [
            'default' => 'claude-sonnet-4-6',
            'models' => [
                ['id' => 'claude-sonnet-4-6', 'name' => 'Claude Sonnet 4.6'],
                ['id' => 'claude-opus-4-8', 'name' => 'Claude Opus 4.8'],
                ['id' => 'claude-opus-4-7', 'name' => 'Claude Opus 4.7'],
                ['id' => 'claude-opus-4-6', 'name' => 'Claude Opus 4.6'],
                ['id' => 'claude-sonnet-4-5-20250929', 'name' => 'Claude Sonnet 4.5'],
                ['id' => 'claude-opus-4-5-20251101', 'name' => 'Claude Opus 4.5'],
            ],
        ],
        'Azure' => [
            'default' => '',
            'models' => [],
        ],
        'AzureImage' => [
            'default' => '',
            'models' => [],
        ],
        'AzureEmbedding' => [
            'default' => '',
            'models' => [],
        ],
        'DeepSeek' => [
            'default' => 'deepseek-v4-flash',
            'models' => [
                ['id' => 'deepseek-v4-flash', 'name' => 'DeepSeek V4 Flash'],
                ['id' => 'deepseek-v4-pro', 'name' => 'DeepSeek V4 Pro'],
            ],
        ],
        'xAI' => [
            'default' => 'grok-4-1-fast-non-reasoning',
            'models' => [
                ['id' => 'grok-4-1-fast-non-reasoning', 'name' => 'Grok 4.1 Fast Non-Reasoning'],
                ['id' => 'grok-4', 'name' => 'Grok 4'],
                ['id' => 'grok-4.20-reasoning', 'name' => 'Grok 4.20 Reasoning'],
            ],
        ],
        'Ollama' => [
            'default' => '',
            'models' => [],
        ],
        'ElevenLabs' => [
            'default' => '',
            'models' => [],
        ],
        'ElevenLabsModels' => [
            'default' => 'eleven_multilingual_v2',
            'models' => ['eleven_multilingual_v2'],
        ],
        'OpenAITTS' => [
            'default' => 'tts-1',
            'models' => [['id' => 'tts-1', 'name' => 'TTS-1'], ['id' => 'tts-1-hd', 'name' => 'TTS-1-HD']],
        ],
        'OpenAISTT' => [
            'default' => 'whisper-1',
            'models' => [['id' => 'whisper-1', 'name' => 'Whisper-1']],
        ],
        'PineconeIndexes' => [
            'default' => '',
            'models' => [],
        ],
        'QdrantCollections' => [
            'default' => '',
            'models' => [],
        ],
        'ChromaCollections' => [
            'default' => '',
            'models' => [],
        ],
        'Replicate' => [
            'default' => '',
            'models' => [],
        ],
    ];

    private static $model_list_options = [
        'OpenAI'           => 'aipkit_openai_model_list',
        'OpenAIEmbedding'  => 'aipkit_openai_embedding_model_list',
        'OpenRouter'       => 'aipkit_openrouter_model_list',
        'OpenRouterEmbedding' => 'aipkit_openrouter_embedding_model_list',
        'Google'           => 'aipkit_google_model_list',
        'GoogleImage'      => 'aipkit_google_image_model_list',
        'xAIImage'         => 'aipkit_xai_image_model_list',
        'GoogleVideo'      => 'aipkit_google_video_model_list',
        'GoogleEmbedding'  => 'aipkit_google_embedding_model_list',
        'Claude'           => 'aipkit_claude_model_list',
        'Azure'            => 'aipkit_azure_deployment_list',
        'AzureImage' => 'aipkit_azure_image_model_list', 'AzureEmbedding'   => 'aipkit_azure_embedding_model_list', 'DeepSeek'         => 'aipkit_deepseek_model_list',
        'xAI'             => 'aipkit_xai_model_list',
        'Ollama'           => 'aipkit_ollama_model_list',
        'ElevenLabs'       => 'aipkit_elevenlabs_voice_list',
        'ElevenLabsModels' => 'aipkit_elevenlabs_model_list',
        'OpenAITTS'        => 'aipkit_openai_tts_model_list',
        'OpenAISTT'        => 'aipkit_openai_stt_model_list',
        'PineconeIndexes'   => 'aipkit_pinecone_index_list',
        'QdrantCollections' => 'aipkit_qdrant_collection_list', // Added Qdrant option
        'ChromaCollections' => 'aipkit_chroma_collection_list',
        'Replicate' => 'aipkit_replicate_model_list',
    ];

    private static $provider_capabilities = [
        'xAI' => [
            'text_generation' => true,
            'streaming' => true,
            'image_input' => true,
            'web_search' => true,
            'embeddings' => false,
            'vector_stores' => false,
            'image_generation' => true,
            'image_editing' => true,
            'video_generation' => false,
            'tts' => false,
            'stt' => false,
            'realtime' => false,
            'legacy_completions' => false,
            'chat_completions' => false,
        ],
    ];

    /** @var array Holds request-level cache for model lists */
    private static $cached_model_lists = [];
    public const MODEL_LIST_TRANSIENT_TTL = 5 * MINUTE_IN_SECONDS;

    public static function normalize_provider_label(string $provider): string
    {
        $provider = sanitize_text_field(trim($provider));
        if ($provider === '') {
            return '';
        }

        foreach (array_keys(self::$provider_defaults) as $known_provider) {
            if (strtolower($known_provider) === strtolower($provider)) {
                return $known_provider;
            }
        }

        return $provider;
    }

    public static function get_provider_display_name(string $provider): string
    {
        $provider = sanitize_text_field(trim($provider));
        if ($provider === '') {
            return '';
        }

        $provider_lower = strtolower($provider);
        if ($provider_lower === 'claude') {
            return __('Anthropic', 'gpt3-ai-content-generator');
        }
        if ($provider_lower === 'claude_files') {
            return __('Anthropic Files', 'gpt3-ai-content-generator');
        }

        return self::normalize_provider_label($provider);
    }

    public static function get_provider_capabilities(string $provider): array
    {
        $normalized_provider = self::normalize_provider_label($provider);
        $default_capabilities = self::$provider_capabilities[$normalized_provider] ?? [];
        $filtered_capabilities = apply_filters(
            'aipkit_provider_capabilities',
            $default_capabilities,
            $normalized_provider
        );

        if (!is_array($filtered_capabilities)) {
            $filtered_capabilities = $default_capabilities;
        } else {
            $filtered_capabilities = array_merge($default_capabilities, $filtered_capabilities);
        }

        $normalized_capabilities = [];
        foreach ($filtered_capabilities as $capability => $supported) {
            if (!is_string($capability)) {
                continue;
            }
            $capability_key = sanitize_key($capability);
            if ($capability_key === '') {
                continue;
            }
            $normalized_capabilities[$capability_key] = (bool) $supported;
        }

        return $normalized_capabilities;
    }

    public static function provider_supports_capability(
        string $provider,
        string $capability,
        bool $default_for_unlisted = true
    ): bool {
        $capability_key = sanitize_key($capability);
        if ($capability_key === '') {
            return false;
        }

        $capabilities = self::get_provider_capabilities($provider);
        if (array_key_exists($capability_key, $capabilities)) {
            return (bool) $capabilities[$capability_key];
        }

        return $default_for_unlisted;
    }

    /**
     * Resolve main provider allowlist for provider-selection flows.
     *
     * Defaults intentionally exclude addon providers. Addons can extend this
     * list using `aipkit_main_provider_allowlist`.
     *
     * @return array<int, string>
     */
    public static function get_main_provider_allowlist(): array
    {
        $default_allowlist = ['OpenAI', 'Google', 'Claude', 'OpenRouter', 'Azure', 'DeepSeek', 'xAI'];
        $filtered_allowlist = apply_filters('aipkit_main_provider_allowlist', $default_allowlist);
        if (!is_array($filtered_allowlist)) {
            $filtered_allowlist = $default_allowlist;
        }

        $valid_provider_keys = array_keys(self::$provider_defaults);
        $blocked_provider_keys = ['ElevenLabs', 'Pexels', 'Pixabay', 'Pinecone', 'Qdrant', 'Chroma', 'Replicate'];
        $normalized = [];

        foreach ($filtered_allowlist as $provider) {
            $provider = sanitize_text_field((string) $provider);
            if (
                $provider === ''
                || !in_array($provider, $valid_provider_keys, true)
                || in_array($provider, $blocked_provider_keys, true)
            ) {
                continue;
            }

            $normalized[$provider] = true;
        }

        if (empty($normalized)) {
            foreach ($default_allowlist as $provider) {
                $normalized[$provider] = true;
            }
        }

        return array_keys($normalized);
    }

    private static function get_model_catalog_entry(string $provider_key): array
    {
        $entry = self::$model_catalog[$provider_key] ?? ['default' => '', 'models' => []];
        if (!is_array($entry)) {
            return ['default' => '', 'models' => []];
        }

        return [
            'default' => isset($entry['default']) ? sanitize_text_field((string) $entry['default']) : '',
            'models' => isset($entry['models']) && is_array($entry['models']) ? $entry['models'] : [],
        ];
    }

    private static function get_catalog_model_rows(string $provider_key): array
    {
        $entry = self::get_model_catalog_entry($provider_key);
        return $entry['models'];
    }

    private static function get_catalog_model_ids(string $provider_key): array
    {
        $models = self::get_catalog_model_rows($provider_key);
        $ids = [];

        foreach ($models as $model) {
            if (is_string($model)) {
                $model_id = sanitize_text_field($model);
            } elseif (is_array($model) && isset($model['id'])) {
                $model_id = sanitize_text_field((string) $model['id']);
            } else {
                $model_id = '';
            }

            if ($model_id === '') {
                continue;
            }

            $ids[] = $model_id;
        }

        return $ids;
    }

    public static function get_default_model_id(string $provider_key): string
    {
        $entry = self::get_model_catalog_entry($provider_key);
        if ($entry['default'] !== '') {
            return $entry['default'];
        }

        $catalog_ids = self::get_catalog_model_ids($provider_key);
        return $catalog_ids[0] ?? '';
    }

    private static function get_hydrated_provider_defaults_all(): array
    {
        $defaults = self::$provider_defaults;
        $provider_model_fields = [
            'OpenAI' => ['model' => 'OpenAI', 'embedding_model' => 'OpenAIEmbedding'],
            'OpenRouter' => ['model' => 'OpenRouter'],
            'Google' => ['model' => 'Google', 'embedding_model' => 'GoogleEmbedding'],
            'Azure' => ['model' => 'Azure', 'embeddings' => 'AzureEmbedding'],
            'Claude' => ['model' => 'Claude'],
            'DeepSeek' => ['model' => 'DeepSeek'],
            'xAI' => ['model' => 'xAI'],
            'Ollama' => ['model' => 'Ollama'],
            'ElevenLabs' => ['model_id' => 'ElevenLabsModels'],
        ];

        foreach ($provider_model_fields as $provider_name => $field_map) {
            if (!isset($defaults[$provider_name]) || !is_array($defaults[$provider_name])) {
                continue;
            }

            foreach ($field_map as $field_name => $catalog_key) {
                $defaults[$provider_name][$field_name] = self::get_default_model_id($catalog_key);
            }
        }

        return $defaults;
    }

    /**
     * Normalize a provider to a valid main-provider value.
     *
     * @param string $provider Raw provider value.
     * @param string $fallback Fallback provider when input is invalid.
     * @return string
     */
    public static function normalize_main_provider(string $provider, string $fallback = 'OpenAI'): string
    {
        $provider = sanitize_text_field(trim($provider));
        $allowed_providers = self::get_main_provider_allowlist();

        if (in_array($provider, $allowed_providers, true)) {
            return $provider;
        }

        if (!in_array($fallback, $allowed_providers, true)) {
            $fallback = $allowed_providers[0] ?? 'OpenAI';
        }

        return $fallback;
    }

    /**
     * Returns the default embedding provider map used across vector flows.
     *
     * @return array<string, string>
     */
    public static function get_default_embedding_provider_map(): array
    {
        return [
            'openai' => 'OpenAI',
            'google' => 'Google',
            'azure' => 'Azure',
            'openrouter' => 'OpenRouter',
        ];
    }

    /**
     * Returns normalized embedding provider map, including filter extensions.
     *
     * @param string $context
     * @return array<string, string>
     */
    public static function get_embedding_provider_map(string $context = ''): array
    {
        $default_map = self::get_default_embedding_provider_map();
        $provider_map = apply_filters('aipkit_embedding_provider_map', $default_map, $context);
        if (!is_array($provider_map)) {
            $provider_map = $default_map;
        }

        $normalized_map = [];
        foreach ($provider_map as $provider_key => $provider_label) {
            if (!is_string($provider_key) || !is_string($provider_label)) {
                continue;
            }
            $provider_key = sanitize_key($provider_key);
            if ($provider_key === '') {
                continue;
            }
            $provider_label = sanitize_text_field($provider_label);
            if (!self::provider_supports_capability($provider_label, 'embeddings')) {
                continue;
            }
            $normalized_map[$provider_key] = $provider_label;
        }

        return empty($normalized_map) ? $default_map : $normalized_map;
    }

    /**
     * Returns normalized embedding provider keys for a given context.
     *
     * @param string $context
     * @return array<int, string>
     */
    public static function get_embedding_provider_keys(string $context = ''): array
    {
        $provider_map = self::get_embedding_provider_map($context);
        $provider_keys = array_values(array_unique(array_map('sanitize_key', array_keys($provider_map))));
        if (empty($provider_keys)) {
            $provider_keys = array_keys(self::get_default_embedding_provider_map());
        }
        return $provider_keys;
    }

    /**
     * Resolves provider key (e.g. "openai") to provider name (e.g. "OpenAI").
     *
     * Returns null when key is not present in the normalized embedding provider map.
     *
     * @param string $provider_key
     * @param string $context
     * @return string|null
     */
    public static function resolve_embedding_provider_name(string $provider_key, string $context = ''): ?string
    {
        $provider_lookup = sanitize_key((string) strtolower($provider_key));
        if ($provider_lookup === '') {
            return null;
        }

        $provider_map = self::get_embedding_provider_map($context);
        if (!isset($provider_map[$provider_lookup]) || !is_string($provider_map[$provider_lookup])) {
            return null;
        }

        $provider_name = sanitize_text_field($provider_map[$provider_lookup]);
        return $provider_name !== '' ? $provider_name : null;
    }

    /**
     * Normalizes provider key (e.g. "openai") to provider name (e.g. "OpenAI").
     *
     * @param string $provider_key
     * @param string $context
     * @return string
     */
    public static function normalize_embedding_provider_name(string $provider_key, string $context = ''): string
    {
        $provider_lookup = sanitize_key((string) strtolower($provider_key));
        $resolved_name = self::resolve_embedding_provider_name($provider_lookup, $context);
        return $resolved_name !== null ? $resolved_name : ucfirst($provider_lookup);
    }

    /**
     * Returns default embedding model rows grouped by provider key.
     *
     * @return array<string, array<int, array{id:string,name:string}>>
     */
    public static function get_default_embedding_models_by_provider(): array
    {
        return [
            'openai' => self::normalize_embedding_model_rows(self::get_openai_embedding_models()),
            'google' => self::normalize_embedding_model_rows(self::get_google_embedding_models()),
            'openrouter' => self::normalize_embedding_model_rows(self::get_openrouter_embedding_models()),
            'azure' => self::normalize_embedding_model_rows(self::get_azure_embedding_models()),
        ];
    }

    /**
     * Returns normalized embedding models by provider, including filter extensions.
     *
     * @param string $context
     * @return array<string, array<int, array{id:string,name:string}>>
     */
    public static function get_embedding_models_by_provider(string $context = ''): array
    {
        $provider_map = self::get_embedding_provider_map($context);
        $default_models_by_provider = self::get_default_embedding_models_by_provider();
        $models_by_provider = apply_filters(
            'aipkit_embedding_models_by_provider',
            $default_models_by_provider,
            $context
        );

        $normalized_models_by_provider = self::normalize_embedding_models_by_provider($models_by_provider);
        if (empty($normalized_models_by_provider)) {
            $normalized_models_by_provider = $default_models_by_provider;
        }

        foreach (array_keys($provider_map) as $provider_key) {
            if (!isset($normalized_models_by_provider[$provider_key])) {
                $normalized_models_by_provider[$provider_key] = $default_models_by_provider[$provider_key] ?? [];
            }
        }

        return $normalized_models_by_provider;
    }

    /**
     * Returns a lookup map of all known embedding model IDs for the given context.
     *
     * Useful for validating whether a saved model still exists in the current
     * provider model lists.
     *
     * @param string $context
     * @return array<string, bool>
     */
    public static function get_all_embedding_model_ids_map(string $context = ''): array
    {
        $models_by_provider = self::get_embedding_models_by_provider($context);
        $model_ids_map = [];

        foreach ($models_by_provider as $provider_models) {
            $normalized_rows = self::normalize_embedding_model_rows($provider_models);
            foreach ($normalized_rows as $model_row) {
                $model_id = isset($model_row['id']) ? sanitize_text_field((string) $model_row['id']) : '';
                if ($model_id !== '') {
                    $model_ids_map[$model_id] = true;
                }
            }
        }

        return $model_ids_map;
    }

    /**
     * Render `<optgroup>` options for embedding model select fields.
     *
     * Supports either plain model values (`model`) or combined provider/model
     * values (`provider_model`, formatted as `provider::model`).
     *
     * @param array<string, string> $provider_options
     * @param array<string, array<int, array{id:string,name:string}>> $models_by_provider
     * @param string $selected_provider
     * @param string $selected_model
     * @param array<string, mixed> $args
     * @return string
     */
    public static function render_embedding_optgroup_options(
        array $provider_options,
        array $models_by_provider,
        string $selected_provider = '',
        string $selected_model = '',
        array $args = []
    ): string {
        $args = wp_parse_args($args, [
            'value_mode' => 'model', // model | provider_model
            'include_manual_fallback' => true,
            'manual_group_label' => __('Manual', 'gpt3-ai-content-generator'),
        ]);

        $value_mode = isset($args['value_mode']) && $args['value_mode'] === 'provider_model'
            ? 'provider_model'
            : 'model';
        $include_manual_fallback = !empty($args['include_manual_fallback']);
        $manual_group_label = sanitize_text_field((string) ($args['manual_group_label'] ?? __('Manual', 'gpt3-ai-content-generator')));

        $normalized_provider_options = [];
        foreach ($provider_options as $provider_key => $provider_label) {
            if (!is_string($provider_key) || !is_string($provider_label)) {
                continue;
            }
            $provider_key = sanitize_key($provider_key);
            if ($provider_key === '') {
                continue;
            }
            $normalized_provider_options[$provider_key] = sanitize_text_field($provider_label);
        }
        if (empty($normalized_provider_options)) {
            $normalized_provider_options = self::get_default_embedding_provider_map();
        }

        $normalized_models_by_provider = self::normalize_embedding_models_by_provider($models_by_provider);
        foreach (array_keys($normalized_provider_options) as $provider_key) {
            if (!isset($normalized_models_by_provider[$provider_key])) {
                $normalized_models_by_provider[$provider_key] = [];
            }
        }

        $selected_provider = sanitize_key($selected_provider);
        $selected_model = sanitize_text_field($selected_model);
        $selected_value = '';
        if ($selected_model !== '') {
            $selected_value = $value_mode === 'provider_model'
                ? ($selected_provider !== '' ? $selected_provider . '::' . $selected_model : '')
                : $selected_model;
        }

        $model_provider_lookup = [];
        foreach ($normalized_models_by_provider as $provider_key => $provider_models) {
            foreach ($provider_models as $model_row) {
                $model_id = isset($model_row['id']) ? sanitize_text_field((string) $model_row['id']) : '';
                if ($model_id === '' || isset($model_provider_lookup[$model_id])) {
                    continue;
                }
                $model_provider_lookup[$model_id] = $provider_key;
            }
        }

        $manual_needed = (
            $include_manual_fallback
            && $selected_model !== ''
            && !isset($model_provider_lookup[$selected_model])
        );

        $html = '';
        foreach ($normalized_provider_options as $provider_key => $provider_label) {
            $provider_models = $normalized_models_by_provider[$provider_key] ?? [];
            $html .= '<optgroup label="' . esc_attr($provider_label) . '">';

            foreach ($provider_models as $model_row) {
                $model_id = isset($model_row['id']) ? sanitize_text_field((string) $model_row['id']) : '';
                if ($model_id === '') {
                    continue;
                }
                $model_name = isset($model_row['name'])
                    ? sanitize_text_field((string) $model_row['name'])
                    : $model_id;
                $option_value = $value_mode === 'provider_model'
                    ? $provider_key . '::' . $model_id
                    : $model_id;
                $is_selected = selected($selected_value, $option_value, false);
                $html .= '<option value="' . esc_attr($option_value) . '" data-provider="' . esc_attr($provider_key) . '" ' . $is_selected . '>' . esc_html($model_name) . '</option>';
            }

            if (
                $manual_needed
                && $value_mode === 'provider_model'
                && $selected_provider !== ''
                && $selected_provider === $provider_key
            ) {
                $manual_value = $selected_provider . '::' . $selected_model;
                $html .= '<option value="' . esc_attr($manual_value) . '" data-provider="' . esc_attr($selected_provider) . '" selected="selected">' . esc_html($selected_model) . '</option>';
                $manual_needed = false;
            }

            $html .= '</optgroup>';
        }

        if ($manual_needed && $selected_model !== '') {
            if ($value_mode === 'provider_model') {
                $manual_provider = $selected_provider !== '' ? $selected_provider : 'manual';
                $manual_value = $manual_provider . '::' . $selected_model;
                $html .= '<optgroup label="' . esc_attr($manual_group_label) . '">';
                $html .= '<option value="' . esc_attr($manual_value) . '" data-provider="' . esc_attr($manual_provider) . '" selected="selected">' . esc_html($selected_model) . '</option>';
                $html .= '</optgroup>';
            } else {
                $manual_provider = $selected_provider !== '' ? $selected_provider : 'manual';
                $html .= '<option value="' . esc_attr($selected_model) . '" data-provider="' . esc_attr($manual_provider) . '" selected="selected">' . esc_html($selected_model) . '</option>';
            }
        }

        return $html;
    }

    /**
     * Returns embedding localization payload for admin UI scripts.
     *
     * Includes both new grouped keys and legacy per-provider keys
     * to keep existing JS modules backward-compatible.
     *
     * @param string $context
     * @param bool $include_legacy Include deprecated per-provider arrays for backward compatibility.
     * @return array<string, mixed>
     */
    public static function get_embedding_localization_payload(string $context = '', bool $include_legacy = true): array
    {
        $provider_map = self::get_embedding_provider_map($context);
        $models_by_provider = self::get_embedding_models_by_provider($context);

        $payload = [
            'embeddingProviderMap' => $provider_map,
            'embeddingModelsByProvider' => $models_by_provider,
            'embeddingModels' => $models_by_provider,
            'embedding_provider_map' => $provider_map,
            'embedding_models_by_provider' => $models_by_provider,
        ];

        if (!$include_legacy) {
            return $payload;
        }

        $openai_models = isset($models_by_provider['openai']) && is_array($models_by_provider['openai'])
            ? $models_by_provider['openai']
            : [];
        $google_models = isset($models_by_provider['google']) && is_array($models_by_provider['google'])
            ? $models_by_provider['google']
            : [];
        $openrouter_models = isset($models_by_provider['openrouter']) && is_array($models_by_provider['openrouter'])
            ? $models_by_provider['openrouter']
            : [];
        $azure_models = isset($models_by_provider['azure']) && is_array($models_by_provider['azure'])
            ? $models_by_provider['azure']
            : [];
        $ollama_models = isset($models_by_provider['ollama']) && is_array($models_by_provider['ollama'])
            ? $models_by_provider['ollama']
            : [];

        return array_merge($payload, [
            'openaiEmbeddingModels' => $openai_models,
            'googleEmbeddingModels' => $google_models,
            'openrouterEmbeddingModels' => $openrouter_models,
            'azureEmbeddingModels' => $azure_models,
            'ollamaEmbeddingModels' => $ollama_models,
            'openai_embedding_models' => $openai_models,
            'google_embedding_models' => $google_models,
            'openrouter_embedding_models' => $openrouter_models,
            'azure_embedding_models' => $azure_models,
            'ollama_embedding_models' => $ollama_models,
        ]);
    }

    /**
     * Returns vector-store localization payload for admin UI scripts.
     *
     * Uses registry data when available and falls back to saved model-list
     * options for Pinecone/Qdrant/Chroma in partial-sync states.
     *
     * @param string $context
     * @return array<string, mixed>
     */
    public static function get_vector_store_localization_payload(string $context = ''): array
    {
        $openai_vector_stores = [];
        $pinecone_indexes = self::get_pinecone_indexes();
        $qdrant_collections = self::get_qdrant_collections();
        $chroma_collections = self::get_chroma_collections();

        if (class_exists(AIPKit_Vector_Store_Registry::class)) {
            $openai_vector_stores = AIPKit_Vector_Store_Registry::get_registered_stores_by_provider('OpenAI');

            $registry_pinecone_indexes = AIPKit_Vector_Store_Registry::get_registered_stores_by_provider('Pinecone');
            if (is_array($registry_pinecone_indexes) && !empty($registry_pinecone_indexes)) {
                $pinecone_indexes = $registry_pinecone_indexes;
            }

            $registry_qdrant_collections = AIPKit_Vector_Store_Registry::get_registered_stores_by_provider('Qdrant');
            if (is_array($registry_qdrant_collections) && !empty($registry_qdrant_collections)) {
                $qdrant_collections = $registry_qdrant_collections;
            }

            $registry_chroma_collections = AIPKit_Vector_Store_Registry::get_registered_stores_by_provider('Chroma');
            if (is_array($registry_chroma_collections) && !empty($registry_chroma_collections)) {
                $chroma_collections = $registry_chroma_collections;
            }
        }

        $payload = [
            'vectorStores' => [
                'openai' => $openai_vector_stores,
                'pinecone' => $pinecone_indexes,
                'qdrant' => $qdrant_collections,
                'chroma' => $chroma_collections,
            ],
            'openaiVectorStores' => $openai_vector_stores,
            'pineconeIndexes' => $pinecone_indexes,
            'qdrantCollections' => $qdrant_collections,
            'chromaCollections' => $chroma_collections,
            'openai_vector_stores' => $openai_vector_stores,
            'pinecone_indexes' => $pinecone_indexes,
            'qdrant_collections' => $qdrant_collections,
            'chroma_collections' => $chroma_collections,
        ];

        $filtered_payload = apply_filters('aipkit_vector_store_localization_payload', $payload, $context);
        return is_array($filtered_payload) ? array_merge($payload, $filtered_payload) : $payload;
    }

    /**
     * Normalize models-by-provider map into `{provider_key => [id,name]}` entries.
     *
     * @param mixed $models_by_provider
     * @return array<string, array<int, array{id:string,name:string}>>
     */
    public static function normalize_embedding_models_by_provider($models_by_provider): array
    {
        $normalized_map = [];
        if (!is_array($models_by_provider)) {
            return $normalized_map;
        }

        foreach ($models_by_provider as $provider_key => $models) {
            if (!is_string($provider_key)) {
                continue;
            }
            $provider_key = sanitize_key($provider_key);
            if ($provider_key === '') {
                continue;
            }
            $normalized_map[$provider_key] = self::normalize_embedding_model_rows($models);
        }

        return $normalized_map;
    }

    /**
     * Normalize mixed model rows to `[id, name]` entries.
     *
     * @param mixed $models
     * @return array<int, array{id:string,name:string}>
     */
    public static function normalize_embedding_model_rows($models): array
    {
        $normalized_models = [];
        if (!is_array($models)) {
            return $normalized_models;
        }

        foreach ($models as $model_row) {
            if (is_string($model_row)) {
                $model_id = sanitize_text_field($model_row);
                if ($model_id !== '') {
                    $normalized_models[] = [
                        'id' => $model_id,
                        'name' => $model_id,
                    ];
                }
                continue;
            }

            if (!is_array($model_row)) {
                continue;
            }

            $model_id = isset($model_row['id']) ? sanitize_text_field((string) $model_row['id']) : '';
            if ($model_id === '' && isset($model_row['name'])) {
                $model_id = sanitize_text_field((string) $model_row['name']);
            }
            if ($model_id === '') {
                continue;
            }

            $model_name = isset($model_row['name'])
                ? sanitize_text_field((string) $model_row['name'])
                : $model_id;

            $normalized_row = $model_row;
            $normalized_row['id'] = $model_id;
            $normalized_row['name'] = $model_name;
            $normalized_models[] = $normalized_row;
        }

        return $normalized_models;
    }

    private static function is_deprecated_deepseek_model_id(string $model_id): bool
    {
        $model_id = strtolower(trim($model_id));
        if (strpos($model_id, 'deepseek-') !== 0) {
            return false;
        }

        $suffix = (string) substr($model_id, strlen('deepseek-'));
        return in_array($suffix, self::DEEPSEEK_DEPRECATED_MODEL_SUFFIXES, true);
    }

    private static function filter_deepseek_model_rows(array $models): array
    {
        $filtered_models = [];
        foreach (self::normalize_embedding_model_rows($models) as $model_row) {
            $model_id = isset($model_row['id']) ? (string) $model_row['id'] : '';
            if ($model_id === '' || self::is_deprecated_deepseek_model_id($model_id)) {
                continue;
            }

            $filtered_models[] = $model_row;
        }

        return $filtered_models;
    }

    private static function merge_model_rows_by_id(array ...$model_lists): array
    {
        $merged_models = [];
        $indexes_by_id = [];

        foreach ($model_lists as $model_list) {
            foreach (self::normalize_embedding_model_rows($model_list) as $model_row) {
                $model_id = isset($model_row['id']) ? sanitize_text_field((string) $model_row['id']) : '';
                if ($model_id === '') {
                    continue;
                }

                if (isset($indexes_by_id[$model_id])) {
                    $existing_index = $indexes_by_id[$model_id];
                    $merged_models[$existing_index] = array_merge($merged_models[$existing_index], $model_row);
                    continue;
                }

                $indexes_by_id[$model_id] = count($merged_models);
                $merged_models[] = $model_row;
            }
        }

        return $merged_models;
    }

    public static function merge_model_rows(array ...$model_lists): array
    {
        return self::merge_model_rows_by_id(...$model_lists);
    }


    public static function get_current_provider()
    {
        $opts = get_option('aipkit_options');
        if (!is_array($opts)) {
            $opts = [];
        }
        $stored_provider = isset($opts['provider']) ? sanitize_text_field((string) $opts['provider']) : 'OpenAI';
        $normalized_provider = self::normalize_main_provider($stored_provider, 'OpenAI');

        if ($normalized_provider !== $stored_provider) {
            $opts['provider'] = $normalized_provider;
            update_option('aipkit_options', $opts, 'no');
        }

        return $normalized_provider;
    }

    public static function get_all_providers()
    {
        $provider_defaults = self::get_hydrated_provider_defaults_all();

        $opts = get_option('aipkit_options');
        if (!is_array($opts)) {
            $opts = [];
        }

        // Check if the providers data is missing or corrupted (not an array).
        if (!isset($opts['providers']) || !is_array($opts['providers'])) {
            // Data is corrupt or missing. Return a default structure for this request only.
            // Crucially, DO NOT save this back to the database. This prevents a temporary read error
            // from causing a permanent wipe of all saved API keys. The next successful save
            // from the settings page will restore the correct structure.
            $temporary_providers = [];
            foreach ($provider_defaults as $provider_name => $defaults) {
                $temporary_providers[$provider_name] = $defaults;
            }
            return $temporary_providers;
        }

        // Data from DB is a valid array. Proceed with normal initialization/pruning.
        $providers_from_db = $opts['providers'];
        $final_providers = [];
        $changed = false;

        // Loop through the master list of defaults to ensure structure is always correct.
        foreach ($provider_defaults as $provider_name => $defaults) {
            $current_settings = $providers_from_db[$provider_name] ?? [];
            if (!is_array($current_settings)) {
                $current_settings = []; // Treat a corrupted entry for a single provider as empty
            }
            // Merge defaults with current settings (current values take precedence).
            $merged = array_merge($defaults, $current_settings);
            // Prune any obsolete settings that are not in the defaults.
            $final_providers[$provider_name] = array_intersect_key($merged, $defaults);
        }

        if (wp_json_encode($providers_from_db) !== wp_json_encode($final_providers)) {
            $opts['providers'] = $final_providers;
            update_option('aipkit_options', $opts, 'no');
        }
        return $final_providers;
    }

    /**
     * Return the connection state used by provider-dependent admin modules.
     *
     * Keeping this map here gives page localization and AJAX saves one
     * authoritative definition of what "configured" means for every provider.
     *
     * @return array<string, bool>
     */
    public static function get_provider_status_map(): array
    {
        $providers = self::get_all_providers();
        $has_value = static function (string $provider, string $key) use ($providers): bool {
            $value = $providers[$provider][$key] ?? '';
            return is_scalar($value) && trim((string) $value) !== '';
        };

        return [
            'openai' => $has_value('OpenAI', 'api_key'),
            'google' => $has_value('Google', 'api_key'),
            'claude' => $has_value('Claude', 'api_key'),
            'openrouter' => $has_value('OpenRouter', 'api_key'),
            'azure' => $has_value('Azure', 'api_key') && $has_value('Azure', 'endpoint'),
            'ollama' => $has_value('Ollama', 'base_url'),
            'deepseek' => $has_value('DeepSeek', 'api_key'),
            'xai' => $has_value('xAI', 'api_key'),
            'replicate' => $has_value('Replicate', 'api_key'),
            'elevenlabs' => $has_value('ElevenLabs', 'api_key'),
            'pexels' => $has_value('Pexels', 'api_key'),
            'pixabay' => $has_value('Pixabay', 'api_key'),
            'pinecone' => $has_value('Pinecone', 'api_key'),
            'qdrant' => $has_value('Qdrant', 'api_key') && $has_value('Qdrant', 'url'),
            'chroma' => $has_value('Chroma', 'url'),
        ];
    }

    public static function get_provider_data($provider)
    {
        $all = self::get_all_providers();
        $provider_defaults = self::get_hydrated_provider_defaults_all();
        $defaults = $provider_defaults[$provider] ?? [];
        $provider_data = isset($all[$provider]) ? array_merge($defaults, $all[$provider]) : $defaults;

        // Backward compatibility: older installs may have an empty stored model.
        if (
            isset($provider_data['model'])
            && trim((string) $provider_data['model']) === ''
            && !empty($defaults['model'])
        ) {
            $provider_data['model'] = $defaults['model'];
        }
        if (
            $provider === 'DeepSeek'
            && isset($provider_data['model'])
            && self::is_deprecated_deepseek_model_id((string) $provider_data['model'])
        ) {
            $provider_data['model'] = $defaults['model'] ?? self::get_default_model_id('DeepSeek');
        }

        return $provider_data;
    }

    public static function get_default_provider_config()
    {
        $currentProvider = self::get_current_provider();
        $provData = self::get_provider_data($currentProvider);
        $all_possible_keys = [];
        foreach (self::get_hydrated_provider_defaults_all() as $def_val) {
            $all_possible_keys = array_merge($all_possible_keys, array_keys($def_val));
        }
        $all_possible_keys = array_unique($all_possible_keys);
        $result = array_fill_keys($all_possible_keys, '');
        $result['provider'] = $currentProvider;
        foreach ($result as $key => $value) {
            if (isset($provData[$key])) {
                $result[$key] = $provData[$key];
            }
        }
        return $result;
    }

    public static function get_provider_defaults($provider)
    {
        $provider_defaults = self::get_hydrated_provider_defaults_all();
        return $provider_defaults[$provider] ?? [];
    }
    public static function get_provider_defaults_all(): array
    {
        return self::get_hydrated_provider_defaults_all();
    }

    public static function get_recommended_models(string $provider_key): array
    {
        $normalized_key = $provider_key;
        $key_lower = strtolower($provider_key);
        if ('openai' === $key_lower) {
            $normalized_key = 'OpenAI';
        } elseif ('openrouter' === $key_lower) {
            $normalized_key = 'OpenRouter';
        } elseif ('google' === $key_lower) {
            $normalized_key = 'Google';
        } elseif ('claude' === $key_lower) {
            $normalized_key = 'Claude';
        } elseif ('deepseek' === $key_lower) {
            $normalized_key = 'DeepSeek';
        } elseif ('xai' === $key_lower) {
            $normalized_key = 'xAI';
        } elseif ('xaiimage' === $key_lower || 'xai_image' === $key_lower || 'xai-image' === $key_lower) {
            $normalized_key = 'xAIImage';
        }

        $recommended_ids = self::get_catalog_model_ids($normalized_key);
        if (empty($recommended_ids)) {
            return [];
        }

        $available_models = self::get_model_list($normalized_key);
        $lookup = [];
        $is_list = is_array($available_models) && array_keys($available_models) === range(0, count($available_models) - 1);

        if ('OpenAI' === $normalized_key && !$is_list) {
            foreach ($available_models as $group_models) {
                if (!is_array($group_models)) {
                    continue;
                }
                foreach ($group_models as $model) {
                    if (!isset($model['id'])) {
                        continue;
                    }
                    $lookup[$model['id']] = $model['name'] ?? $model['id'];
                }
            }
        } elseif (is_array($available_models)) {
            foreach ($available_models as $model) {
                if (!is_array($model) || !isset($model['id'])) {
                    continue;
                }
                $lookup[$model['id']] = $model['name'] ?? $model['id'];
            }
        }

        $recommended = [];
        foreach ($recommended_ids as $model_id) {
            if (isset($lookup[$model_id])) {
                $recommended[] = [
                    'id' => $model_id,
                    'name' => $lookup[$model_id],
                ];
            }
        }

        /**
         * Filters recommended models for a provider.
         *
         * @param array  $recommended Recommended model list as [{id,name}].
         * @param string $normalized_key Provider key (OpenAI, OpenRouter, Google).
         * @param array  $recommended_ids Recommended model IDs in order.
         * @param array  $lookup Available model lookup [id => name].
         */
        return apply_filters('aipkit_recommended_models', $recommended, $normalized_key, $recommended_ids, $lookup);
    }

    public static function save_provider_data($provider, $data)
    {
        $provider_defaults = self::get_hydrated_provider_defaults_all();

        $opts = get_option('aipkit_options');
        if (!is_array($opts)) {
            $opts = [];
        }

        if (!isset($opts['providers']) || !is_array($opts['providers'])) {
            $opts['providers'] = array();
        }
        if (!isset($opts['providers'][$provider]) || !is_array($opts['providers'][$provider])) {
            $opts['providers'][$provider] = $provider_defaults[$provider] ?? [];
        }

        $current_provider_settings_ref = & $opts['providers'][$provider];
        $defaults_for_provider = $provider_defaults[$provider] ?? [];
        $changed_for_this_provider = false;

        foreach ($defaults_for_provider as $key => $default_value) {
            if (array_key_exists($key, $data)) { // Only process keys that were sent in $data
                $new_value = $data[$key]; // Use the value from $data
                // Sanitize based on key
                if (in_array($key, ['base_url', 'endpoint', 'url'], true)) {
                    $new_value = esc_url_raw($new_value);
                } elseif ($key === 'store_conversation') {
                    $new_value = ($new_value === '1' ? '1' : '0');
                } elseif ($key === 'expiration_policy') {
                    $new_value = absint($new_value);
                } // Sanitize new field
                else {
                    $new_value = sanitize_text_field($new_value);
                }

                if (!isset($current_provider_settings_ref[$key]) || $current_provider_settings_ref[$key] !== $new_value) {
                    $current_provider_settings_ref[$key] = $new_value;
                    $changed_for_this_provider = true;
                }
            }
        }
        if ($changed_for_this_provider) {
            update_option('aipkit_options', $opts, 'no');
        }
    }

    public static function save_current_provider($provider)
    {
        $provider_defaults = self::get_hydrated_provider_defaults_all();

        $opts = get_option('aipkit_options');
        if (!is_array($opts)) {
            $opts = [];
        }

        $provider = self::normalize_main_provider((string) $provider, 'OpenAI');
        if (!isset($opts['provider']) || $opts['provider'] !== $provider) {
            $opts['provider'] = $provider;
            if (!isset($opts['providers']) || !is_array($opts['providers'])) {
                $opts['providers'] = array();
            }
            if (!isset($opts['providers'][$provider]) || !is_array($opts['providers'][$provider])) {
                $opts['providers'][$provider] = $provider_defaults[$provider] ?? [];
            }
            update_option('aipkit_options', $opts, 'no');
        }
    }

    public static function get_model_list(string $provider_key): array
    {
        if (!isset(self::$model_list_options[$provider_key])) {
            return [];
        }

        // 1. Check static request-level cache
        if (isset(self::$cached_model_lists[$provider_key])) {
            return self::$cached_model_lists[$provider_key];
        }

        // 2. Check transient cache
        $transient_key = 'aipkit_' . strtolower($provider_key) . '_models_cache';
        $cached_value = get_transient($transient_key);
        if ($cached_value !== false) {
            self::$cached_model_lists[$provider_key] = $cached_value;
            return $cached_value;
        }

        // 3. Fetch from options (database)
        $option_name = self::$model_list_options[$provider_key];
        $model_list_from_option = get_option($option_name, []);
        $processed_model_list = [];
        $use_defaults = (empty($model_list_from_option) || !is_array($model_list_from_option));

        if ($use_defaults) {
            $default_list_raw = self::get_catalog_model_rows($provider_key);
            if (in_array($provider_key, ['Google', 'Claude', 'Azure', 'DeepSeek', 'OpenRouter'])) {
                $processed_model_list = self::normalize_embedding_model_rows($default_list_raw);
            } elseif ($provider_key === 'OpenAI') {
                $formatted_list = array_map(fn ($id) => ['id' => $id, 'name' => $id], $default_list_raw);
                if (class_exists(AIPKit_Models_API::class)) {
                    $processed_model_list = AIPKit_Models_API::group_openai_models($formatted_list);
                } else {
                    $fb_groups = ['gpt-5 models' => [], 'gpt-4 models' => [], 'gpt-3.5 models' => [], 'fine-tuned models' => [], 'o1 models' => [], 'o3 models' => [], 'o4 models' => [], 'others' => []];
                    foreach ($formatted_list as $item) {
                        $idL = strtolower($item['id']);
                        if (strpos($item['id'], 'ft:') === 0 || strpos($item['id'], ':ft-') !== false) {
                            $fb_groups['fine-tuned models'][] = $item;
                        } elseif (strpos($idL, 'gpt-5') !== false) {
                            $fb_groups['gpt-5 models'][] = $item;
                        } elseif (strpos($idL, 'gpt-4') !== false) {
                            $fb_groups['gpt-4 models'][] = $item;
                        } elseif (strpos($idL, 'gpt-3.5') !== false) {
                            $fb_groups['gpt-3.5 models'][] = $item;
                        } elseif (strpos($idL, 'o1') !== false) {
                            $fb_groups['o1 models'][] = $item;
                        } elseif (strpos($idL, 'o3') !== false) {
                            $fb_groups['o3 models'][] = $item;
                        } elseif (strpos($idL, 'o4') !== false) {
                            $fb_groups['o4 models'][] = $item;
                        } else {
                            $fb_groups['others'][] = $item;
                        }
                    }
                    $processed_model_list = array_filter($fb_groups);
                }
            } else {
                $processed_model_list = $default_list_raw;
            }
        } else {
            $processed_model_list = $model_list_from_option;
            if ($provider_key === 'DeepSeek') {
                $processed_model_list = self::merge_model_rows_by_id(self::get_catalog_model_rows($provider_key), $processed_model_list);
            }
        }
        $processed_model_list = is_array($processed_model_list) ? $processed_model_list : [];
        if ($provider_key === 'DeepSeek') {
            $processed_model_list = self::filter_deepseek_model_rows($processed_model_list);
        }

        // 4. Store in caches
        self::$cached_model_lists[$provider_key] = $processed_model_list;
        set_transient($transient_key, $processed_model_list, self::MODEL_LIST_TRANSIENT_TTL);

        return $processed_model_list;
    }

    /**
     * Clears all model list caches (static and transient).
     * Called after a model sync operation.
     */
    public static function clear_model_caches(): void
    {
        self::$cached_model_lists = []; // Clear static cache
        foreach (array_keys(self::$model_list_options) as $provider_key) {
            $transient_key = 'aipkit_' . strtolower($provider_key) . '_models_cache';
            delete_transient($transient_key);
        }
    }


    public static function get_openai_models(): array
    {
        return self::get_model_list('OpenAI');
    }
    public static function get_openai_image_models(): array
    {
        return self::OPENAI_IMAGE_MODELS;
    }
    public static function get_default_openai_image_model(): string
    {
        return self::OPENAI_DEFAULT_IMAGE_MODEL;
    }
    public static function get_xai_image_models(): array
    {
        $catalog_models = self::get_catalog_model_rows('xAIImage');
        $synced_models = self::get_model_list('xAIImage');

        return self::merge_model_rows_by_id($catalog_models, $synced_models);
    }
    public static function get_default_xai_image_model(): string
    {
        return self::get_default_model_id('xAIImage');
    }
    public static function get_xai_image_model_ids(): array
    {
        return wp_list_pluck(self::get_xai_image_models(), 'id');
    }
    public static function is_supported_xai_image_model(string $model): bool
    {
        return in_array(trim($model), self::get_xai_image_model_ids(), true);
    }
    public static function normalize_xai_image_model(?string $model): string
    {
        $normalized_model = is_string($model) ? trim($model) : '';
        if ($normalized_model !== '' && self::is_supported_xai_image_model($normalized_model)) {
            return $normalized_model;
        }

        return self::get_default_xai_image_model();
    }
    public static function get_openai_image_model_ids(): array
    {
        return wp_list_pluck(self::get_openai_image_models(), 'id');
    }
    public static function is_openai_gpt_image_model(string $model): bool
    {
        $normalized_model = strtolower(trim($model));

        return $normalized_model !== '' && strpos($normalized_model, 'gpt-image') === 0;
    }
    public static function openai_image_model_supports_transparent_background(string $model): bool
    {
        $normalized_model = strtolower(trim($model));

        return self::is_openai_gpt_image_model($normalized_model)
            && strncmp($normalized_model, 'gpt-image-2', strlen('gpt-image-2')) !== 0;
    }
    public static function is_supported_openai_image_model(string $model): bool
    {
        return in_array(trim($model), self::get_openai_image_model_ids(), true);
    }
    public static function normalize_openai_image_model(?string $model): string
    {
        $normalized_model = is_string($model) ? trim($model) : '';

        if ($normalized_model !== '' && self::is_supported_openai_image_model($normalized_model)) {
            return $normalized_model;
        }

        return self::get_default_openai_image_model();
    }
    public static function get_openai_embedding_models(): array
    {
        return self::get_model_list('OpenAIEmbedding');
    }
    public static function get_openrouter_models(): array
    {
        return self::filter_openrouter_text_models(self::get_model_list('OpenRouter'));
    }
    public static function get_openrouter_image_models(): array
    {
        $models = self::get_model_list('OpenRouter');
        if (!is_array($models) || empty($models)) {
            return [];
        }

        $resolver_fn = '\WPAICG\Core\Providers\OpenRouter\Methods\resolve_model_capabilities_from_metadata_logic';
        if (!function_exists($resolver_fn)) {
            $capability_file = WPAICG_PLUGIN_DIR . 'classes/core/providers/openrouter/methods.php';
            if (file_exists($capability_file)) {
                require_once $capability_file;
            }
        }

        $image_models = [];
        foreach ($models as $model) {
            if (!is_array($model) || empty($model['id'])) {
                continue;
            }

            $model_id = sanitize_text_field((string) $model['id']);
            $model_name = isset($model['name']) ? sanitize_text_field((string) $model['name']) : $model_id;
            if ($model_id === '') {
                continue;
            }

            $capabilities = function_exists($resolver_fn)
                ? (array) call_user_func($resolver_fn, $model)
                : [];
            $supports_image = !empty($capabilities['image_output']) || !empty($capabilities['image_generation']);
            if (!$supports_image) {
                continue;
            }

            $item = [
                'id' => $model_id,
                'name' => $model_name,
            ];
            if (isset($model['output_modalities']) && is_array($model['output_modalities'])) {
                $normalized_output_modalities = array_values(array_unique(array_map(
                    static fn($modality): string => strtolower(trim((string) $modality)),
                    $model['output_modalities']
                )));
                $normalized_output_modalities = array_values(array_filter($normalized_output_modalities, static fn($modality): bool => $modality !== ''));
                if (!empty($normalized_output_modalities)) {
                    $item['output_modalities'] = $normalized_output_modalities;
                }
            }
            if (isset($model['supported_parameters']) && is_array($model['supported_parameters'])) {
                $normalized_supported_parameters = array_values(array_unique(array_map(
                    static fn($parameter): string => strtolower(trim((string) $parameter)),
                    $model['supported_parameters']
                )));
                $normalized_supported_parameters = array_values(array_filter($normalized_supported_parameters, static fn($parameter): bool => $parameter !== ''));
                if (!empty($normalized_supported_parameters)) {
                    $item['supported_parameters'] = $normalized_supported_parameters;
                }
            }
            if (!empty($capabilities)) {
                $item['capabilities'] = $capabilities;
            }
            $image_models[] = $item;
        }

        usort(
            $image_models,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        return $image_models;
    }

    private static function filter_openrouter_text_models(array $models): array
    {
        $text_models = [];
        foreach ($models as $model) {
            if (!is_array($model)) {
                $text_models[] = $model;
                continue;
            }

            $output_modalities = isset($model['output_modalities']) && is_array($model['output_modalities'])
                ? array_values(array_unique(array_map(
                    static fn($modality): string => strtolower(trim((string) $modality)),
                    $model['output_modalities']
                )))
                : [];
            $output_modalities = array_values(array_filter($output_modalities, static fn($modality): bool => $modality !== ''));

            if (!empty($output_modalities) && in_array('image', $output_modalities, true) && !in_array('text', $output_modalities, true)) {
                continue;
            }

            $text_models[] = $model;
        }

        return $text_models;
    }
    public static function get_openrouter_embedding_models(): array
    {
        return self::get_model_list('OpenRouterEmbedding');
    }
    public static function get_google_models(): array
    {
        return self::get_model_list('Google');
    }
    public static function get_google_embedding_models(): array
    {
        return self::get_model_list('GoogleEmbedding');
    }
    public static function get_claude_models(): array
    {
        return self::get_model_list('Claude');
    }
    public static function get_google_image_models(): array
    {
        $catalog_models = self::get_catalog_model_rows('GoogleImage');
        $synced_models = self::get_model_list('GoogleImage');

        return self::merge_model_rows_by_id($catalog_models, $synced_models);
    }
    public static function get_default_google_image_model(): string
    {
        return self::get_default_model_id('GoogleImage');
    }
    public static function get_google_video_models(): array
    {
        return self::get_model_list('GoogleVideo');
    }
    public static function get_azure_deployments(): array
    {
        return self::get_model_list('Azure');
    }
    
    /**
     * Get all Azure models grouped by type for dashboard display
     * @return array Grouped array with chat, embedding, and image models
     */
    public static function get_azure_all_models_grouped(): array
    {
        $grouped = [];
        
        // Get chat/language models
        $chat_models = self::get_model_list('Azure');
        if (!empty($chat_models)) {
            $grouped['Chat Models'] = $chat_models;
        }
        
        // Get embedding models
        $embedding_models = self::get_model_list('AzureEmbedding');
        if (!empty($embedding_models)) {
            $grouped['Embedding Models'] = $embedding_models;
        }
        
        // Get image models
        $image_models = self::get_model_list('AzureImage');
        if (!empty($image_models)) {
            $grouped['Image Models'] = $image_models;
        }
        
        return $grouped;
    }
    
    public static function get_azure_image_models(): array
    {
        return self::get_model_list('AzureImage');
    }
    public static function get_azure_embedding_models(): array { return self::get_model_list('AzureEmbedding'); }
    public static function get_deepseek_models(): array
    {
        return self::get_model_list('DeepSeek');
    }

    /**
     * @param mixed $modalities
     * @return array<int, string>
     */
    private static function normalize_xai_modality_list($modalities): array
    {
        if (!is_array($modalities)) {
            return [];
        }

        $normalized = [];
        foreach ($modalities as $modality) {
            if (!is_string($modality)) {
                continue;
            }
            $modality = strtolower(trim($modality));
            if ($modality !== '') {
                $normalized[] = $modality;
            }
        }

        return array_values(array_unique($normalized));
    }

    private static function xai_model_row_matches_id(string $target_model_id, $model): bool
    {
        $target_model_id = strtolower(trim($target_model_id));
        if ($target_model_id === '') {
            return false;
        }

        if (is_string($model)) {
            return strtolower(trim($model)) === $target_model_id;
        }

        if (!is_array($model)) {
            return false;
        }

        $candidates = [];
        foreach (['id', 'model', 'name'] as $key) {
            if (isset($model[$key]) && is_string($model[$key])) {
                $candidates[] = $model[$key];
            }
        }
        if (isset($model['aliases']) && is_array($model['aliases'])) {
            foreach ($model['aliases'] as $alias) {
                if (is_string($alias)) {
                    $candidates[] = $alias;
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (strtolower(trim((string) $candidate)) === $target_model_id) {
                return true;
            }
        }

        return false;
    }

    public static function xai_model_supports_image_input(string $model_id): bool
    {
        $model_id = sanitize_text_field(trim($model_id));
        if ($model_id === '') {
            return false;
        }

        $models = self::get_xai_models();
        foreach ($models as $model) {
            if (!self::xai_model_row_matches_id($model_id, $model)) {
                continue;
            }

            if (!is_array($model)) {
                return true;
            }

            $input_modalities = self::normalize_xai_modality_list(
                $model['input_modalities'] ?? ($model['modalities']['input'] ?? [])
            );
            if (!empty($input_modalities)) {
                return in_array('image', $input_modalities, true)
                    || in_array('input_image', $input_modalities, true)
                    || in_array('image_url', $input_modalities, true);
            }

            if (isset($model['capabilities']) && is_array($model['capabilities'])) {
                foreach (['image_input', 'vision'] as $capability_key) {
                    if (array_key_exists($capability_key, $model['capabilities'])) {
                        return (bool) $model['capabilities'][$capability_key];
                    }
                }
            }

            return true;
        }

        return true;
    }

    public static function get_xai_models(): array
    {
        return self::get_model_list('xAI');
    }
    public static function get_ollama_models(): array
    {
        return self::get_model_list('Ollama');
    }
    public static function get_elevenlabs_voices(): array
    {
        return self::get_model_list('ElevenLabs');
    }
    public static function get_elevenlabs_models(): array
    {
        return self::get_model_list('ElevenLabsModels');
    }
    public static function get_openai_tts_models(): array
    {
        return self::get_model_list('OpenAITTS');
    }
    public static function get_openai_stt_models(): array
    {
        return self::get_model_list('OpenAISTT');
    }
    public static function get_pinecone_indexes(): array
    {
        return self::get_model_list('PineconeIndexes');
    }
    public static function get_qdrant_collections(): array
    {
        return self::get_model_list('QdrantCollections');
    }
    public static function get_chroma_collections(): array
    {
        return self::get_model_list('ChromaCollections');
    }
    public static function get_replicate_models(): array
    {
        return self::get_model_list('Replicate');
    }
}
