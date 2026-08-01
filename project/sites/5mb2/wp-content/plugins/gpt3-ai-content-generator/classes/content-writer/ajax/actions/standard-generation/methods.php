<?php

namespace WPAICG\ContentWriter\Ajax\Actions\StandardGeneration;

use WPAICG\ContentWriter\Ajax\Actions\AIPKit_Content_Writer_Standard_Generation_Action;
use WP_Error;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Summarizer;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Meta_Prompt_Builder;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Keyword_Prompt_Builder;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Excerpt_Prompt_Builder;
use WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Tags_Prompt_Builder;
use WPAICG\ContentWriter\AIPKit_Content_Writer_Image_Handler;
use WPAICG\ContentWriter\AIPKit_Content_Writer_Output_Cleaner;
use WPAICG\ContentWriter\SEO\AIPKit_Content_Writer_Smart_SEO_Keyword_Resolver;
use WPAICG\Chat\Storage\LogStorage;
use WPAICG\Core\AIPKit_Event_Webhooks;
use function WPAICG\ContentWriter\Ajax\Actions\Shared\load_smart_seo_keyword_resolver_logic;
use function WPAICG\ContentWriter\Ajax\Actions\Shared\merge_smart_seo_usage_logic;
use function WPAICG\ContentWriter\Ajax\Actions\Shared\smart_seo_keyword_resolution_response_fields_logic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Makes the call to the AI provider using the AI Caller.
 *
 * @param AIPKit_Content_Writer_Standard_Generation_Action $handler The handler instance.
 * @param string $provider The AI provider.
 * @param string $model The AI model.
 * @param array $messages The message payload for the API.
 * @param array $ai_params_override AI parameters to override globals.
 * @param string $system_instruction The system instruction for the AI.
 * @return array|WP_Error The result from the AI Caller.
 */
function call_ai_provider_logic(
    AIPKit_Content_Writer_Standard_Generation_Action $handler,
    string $provider,
    string $model,
    array $messages,
    array $ai_params_override,
    string $system_instruction
) {
    return $handler->get_ai_caller()->make_standard_call(
        $provider,
        $model,
        $messages,
        $ai_params_override,
        $system_instruction,
        []
    );
}

/**
 * Handles an error response from the AI call by logging it and sending a JSON error.
 *
 * @param AIPKit_Content_Writer_Standard_Generation_Action $handler The handler instance.
 * @param WP_Error $error The error object returned from the AI call.
 * @param array $validated_params The validated parameters from the request.
 * @param string $conversation_uuid The UUID of this interaction.
 * @return void
 */
function handle_error_response_logic(AIPKit_Content_Writer_Standard_Generation_Action $handler, WP_Error $error, array $validated_params, string $conversation_uuid): void
{
    if ($handler->log_storage) {
        $error_data = $error->get_error_data() ?? [];
        $request_payload_log_on_error = is_array($error_data) ? ($error_data['request_payload_log'] ?? null) : null;

        $handler->log_storage->log_message(array_merge($handler->build_content_writer_log_base(
            $conversation_uuid,
            (string) ($validated_params['provider'] ?? ''),
            (string) ($validated_params['model'] ?? '')
        ), [
            'message_role'      => 'bot',
            'message_content'   => "Error generating content (AJAX): " . $error->get_error_message(),
            'request_payload'   => $request_payload_log_on_error,
        ]));
    }
    $handler->send_wp_error($error);
}

/**
 * Handles a successful response from the AI call by logging it and sending a JSON success response.
 * @param AIPKit_Content_Writer_Standard_Generation_Action $handler The handler instance.
 * @param array $result The successful result array from the AI call.
 * @param array $validated_params The validated parameters from the request.
 * @param string $conversation_uuid The UUID of this interaction.
 * @return void
 */
function handle_success_response_logic(AIPKit_Content_Writer_Standard_Generation_Action $handler, array $result, array $validated_params, string $conversation_uuid): void
{
    $content = $result['content'] ?? '';
    if (class_exists(AIPKit_Content_Writer_Output_Cleaner::class)) {
        $initial_keywords = !empty($validated_params['inline_keywords']) ? $validated_params['inline_keywords'] : ($validated_params['content_keywords'] ?? '');
        $initial_focus_keyword = trim((string) explode(',', (string) $initial_keywords)[0]);
        $content = AIPKit_Content_Writer_Output_Cleaner::clean_article_content((string) $content, $initial_focus_keyword);
    }
    $usage = $result['usage'] ?? null;
    $request_payload_log = $result['request_payload_log'] ?? null;
    $meta_description = null;
    $focus_keyword = null;
    $excerpt = null;
    $tags = null;
    $smart_seo_keyword_resolution = isset($validated_params['smart_seo_keyword_resolution']) && is_array($validated_params['smart_seo_keyword_resolution'])
        ? $validated_params['smart_seo_keyword_resolution']
        : [];

    // Log main content generation
    if ($handler->log_storage) {
        $handler->log_storage->log_message(array_merge($handler->build_content_writer_log_base(
            $conversation_uuid,
            (string) ($validated_params['provider'] ?? ''),
            (string) ($validated_params['model'] ?? '')
        ), [
            'message_role'      => 'bot',
            'message_content'   => $content,
            'usage'             => $usage,
            'request_payload'   => $request_payload_log,
        ]));
    }

    $final_title = $validated_params['content_title'] ?? '';
    $user_provided_keywords = !empty($validated_params['inline_keywords']) ? $validated_params['inline_keywords'] : ($validated_params['content_keywords'] ?? '');

    $keywords_for_prompts = $user_provided_keywords;

    // 1. Generate Focus Keyword FIRST if needed.
    $generate_keyword = ($validated_params['generate_focus_keyword'] ?? '0') === '1';
    if ($generate_keyword && empty($user_provided_keywords) && !empty($content)) {
        $content_summary_for_kw = \WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Summarizer::summarize($content);
        $keyword_user_prompt = \WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Keyword_Prompt_Builder::build($final_title, $content_summary_for_kw, 'custom', $validated_params['custom_keyword_prompt']);
        $keyword_ai_params = ['temperature' => 1, 'top_p' => null];
        $keyword_result = $handler->get_ai_caller()->make_standard_call(
            $validated_params['provider'],
            $validated_params['model'],
            [['role' => 'user', 'content' => $keyword_user_prompt]],
            $keyword_ai_params,
            'You are an SEO expert. Your task is to provide the single best focus keyword for a piece of content.'
        );
        if (!is_wp_error($keyword_result) && !empty($keyword_result['content'])) {
            $focus_keyword = trim(str_replace(['"', "'", '.'], '', $keyword_result['content']));
            $keywords_for_prompts = $focus_keyword; // Use this new keyword for other SEO prompts
            $keyword_usage = $keyword_result['usage'] ?? null;
            load_smart_seo_keyword_resolver_logic();
            if (class_exists(AIPKit_Content_Writer_Smart_SEO_Keyword_Resolver::class) && $handler->get_ai_caller()) {
                $keyword_resolver = new AIPKit_Content_Writer_Smart_SEO_Keyword_Resolver();
                $keyword_resolution = $keyword_resolver->maybe_resolve_keyword(
                    $focus_keyword,
                    $validated_params,
                    $handler->get_ai_caller(),
                    [
                        'ai_provider' => $validated_params['provider'] ?? '',
                        'ai_model' => $validated_params['model'] ?? '',
                        'topic' => $validated_params['content_title'] ?? '',
                        'title' => $final_title,
                        'content_summary' => $content_summary_for_kw,
                    ]
                );
                if (!empty($keyword_resolution['changed'])) {
                    $focus_keyword = (string) $keyword_resolution['keyword'];
                    $keywords_for_prompts = $focus_keyword;
                    $keyword_usage = merge_smart_seo_usage_logic($keyword_usage, $keyword_resolution['usage'] ?? null);
                    $keyword_resolution['source'] = 'generated';
                    $keyword_resolution['resolved_content_title'] = $final_title;
                    $smart_seo_keyword_resolution = $keyword_resolution;
                }
            }
            // Log keyword step
            if ($handler->log_storage) {
                $base = $handler->build_content_writer_log_base($conversation_uuid, (string) $validated_params['provider'], (string) $validated_params['model']);
                $handler->log_storage->log_message(array_merge($base, [
                    'message_role' => 'user',
                    'message_content' => 'Generate Focus Keyword',
                    'request_payload' => [
                        'title' => $final_title,
                        'custom_keyword_prompt' => $validated_params['custom_keyword_prompt'] ?? null,
                    ],
                ]));
                $handler->log_storage->log_message(array_merge($base, [
                    'message_role' => 'bot',
                    'message_content' => $focus_keyword,
                    'usage' => $keyword_usage,
                    'request_payload' => [
                        'payload_sent' => [
                            'messages' => [['role' => 'user', 'content' => $keyword_user_prompt]],
                            'ai_params' => $keyword_ai_params,
                        ],
                    ],
                ]));
            }
        }
    } elseif ($generate_keyword) {
        $focus_keyword = explode(',', $user_provided_keywords)[0];
    }

    $content_summary = \WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Summarizer::summarize($content);

    // 2. Generate Excerpt
    if (($validated_params['generate_excerpt'] ?? '0') === '1' && !empty($content)) {
        $excerpt_user_prompt = \WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Excerpt_Prompt_Builder::build($final_title, $content_summary, $keywords_for_prompts, 'custom', $validated_params['custom_excerpt_prompt']);
        $excerpt_ai_params = ['temperature' => 1, 'top_p' => null];
        $excerpt_result = $handler->get_ai_caller()->make_standard_call($validated_params['provider'], $validated_params['model'], [['role' => 'user', 'content' => $excerpt_user_prompt]], $excerpt_ai_params);
        if (!is_wp_error($excerpt_result) && !empty($excerpt_result['content'])) {
            $excerpt = trim(str_replace(['"', "'"], '', $excerpt_result['content']));
            if ($handler->log_storage) {
                $base = $handler->build_content_writer_log_base($conversation_uuid, (string) $validated_params['provider'], (string) $validated_params['model']);
                $handler->log_storage->log_message(array_merge($base, [
                    'message_role' => 'user',
                    'message_content' => 'Generate Excerpt',
                    'request_payload' => [
                        'title' => $final_title,
                        'keywords' => $keywords_for_prompts,
                        'custom_excerpt_prompt' => $validated_params['custom_excerpt_prompt'] ?? null,
                    ],
                ]));
                $handler->log_storage->log_message(array_merge($base, [
                    'message_role' => 'bot',
                    'message_content' => $excerpt,
                    'usage' => $excerpt_result['usage'] ?? null,
                    'request_payload' => [
                        'payload_sent' => [
                            'messages' => [['role' => 'user', 'content' => $excerpt_user_prompt]],
                            'ai_params' => $excerpt_ai_params,
                        ],
                    ],
                ]));
            }
        }
    }

    // 3. Generate Tags
    if (($validated_params['generate_tags'] ?? '0') === '1' && !empty($content)) {
        $tags_user_prompt = \WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Tags_Prompt_Builder::build($final_title, $content_summary, $keywords_for_prompts, 'custom', $validated_params['custom_tags_prompt']);
        $tags_ai_params = ['temperature' => 0.5, 'top_p' => null];
        $tags_result = $handler->get_ai_caller()->make_standard_call($validated_params['provider'], $validated_params['model'], [['role' => 'user', 'content' => $tags_user_prompt]], $tags_ai_params);
        if (!is_wp_error($tags_result) && !empty($tags_result['content'])) {
            $tags = trim(str_replace(['"', "'"], '', $tags_result['content']));
            if ($handler->log_storage) {
                $base = $handler->build_content_writer_log_base($conversation_uuid, (string) $validated_params['provider'], (string) $validated_params['model']);
                $handler->log_storage->log_message(array_merge($base, [
                    'message_role' => 'user',
                    'message_content' => 'Generate Tags',
                    'request_payload' => [
                        'title' => $final_title,
                        'keywords' => $keywords_for_prompts,
                        'custom_tags_prompt' => $validated_params['custom_tags_prompt'] ?? null,
                    ],
                ]));
                $handler->log_storage->log_message(array_merge($base, [
                    'message_role' => 'bot',
                    'message_content' => $tags,
                    'usage' => $tags_result['usage'] ?? null,
                    'request_payload' => [
                        'payload_sent' => [
                            'messages' => [['role' => 'user', 'content' => $tags_user_prompt]],
                            'ai_params' => $tags_ai_params,
                        ],
                    ],
                ]));
            }
        }
    }

    // 4. Generate Meta Description
    if (($validated_params['generate_meta_description'] ?? '0') === '1' && !empty($content)) {
        $meta_user_prompt = \WPAICG\ContentWriter\Prompt\AIPKit_Content_Writer_Meta_Prompt_Builder::build($final_title, $content_summary, $keywords_for_prompts, 'custom', $validated_params['custom_meta_prompt']);
        $meta_ai_params = ['temperature' => 1, 'top_p' => null];
        $meta_result = $handler->get_ai_caller()->make_standard_call($validated_params['provider'], $validated_params['model'], [['role' => 'user', 'content' => $meta_user_prompt]], $meta_ai_params);
        if (!is_wp_error($meta_result) && !empty($meta_result['content'])) {
            $meta_description = AIPKit_Content_Writer_Output_Cleaner::clean_meta_description((string) $meta_result['content']);
            if ($handler->log_storage) {
                $base = $handler->build_content_writer_log_base($conversation_uuid, (string) $validated_params['provider'], (string) $validated_params['model']);
                $handler->log_storage->log_message(array_merge($base, [
                    'message_role' => 'user',
                    'message_content' => 'Generate Meta Description',
                    'request_payload' => [
                        'title' => $final_title,
                        'keywords' => $keywords_for_prompts,
                        'custom_meta_prompt' => $validated_params['custom_meta_prompt'] ?? null,
                    ],
                ]));
                $handler->log_storage->log_message(array_merge($base, [
                    'message_role' => 'bot',
                    'message_content' => $meta_description,
                    'usage' => $meta_result['usage'] ?? null,
                    'request_payload' => [
                        'payload_sent' => [
                            'messages' => [['role' => 'user', 'content' => $meta_user_prompt]],
                            'ai_params' => $meta_ai_params,
                        ],
                    ],
                ]));
            }
        }
    }
    if (class_exists(AIPKit_Event_Webhooks::class) && !empty($content)) {
        $actor_user_id = get_current_user_id();
        AIPKit_Event_Webhooks::emit(
            'content.generated',
            [
                'title' => $final_title,
                'content' => $content,
                'excerpt' => $excerpt,
                'meta_description' => $meta_description,
                'focus_keyword' => $focus_keyword,
                'tags' => $tags,
                'conversation' => [
                    'id' => $conversation_uuid,
                ],
                'ai' => [
                    'provider' => $validated_params['provider'],
                    'model' => $validated_params['model'],
                ],
                'input' => [
                    'keywords' => $keywords_for_prompts,
                    'source_url' => $validated_params['source_url'] ?? '',
                    'content_length' => $validated_params['content_length'] ?? '',
                ],
                'actor' => [
                    'type' => $actor_user_id ? 'user' : 'guest',
                    'user_id' => $actor_user_id ?: null,
                ],
            ],
            [
                'module' => 'content_writer',
                'origin' => 'direct_standard',
                'resource' => [
                    'type' => 'content_generation',
                    'id' => $conversation_uuid,
                    'label' => $final_title !== '' ? $final_title : __('Generated content', 'gpt3-ai-content-generator'),
                ],
                'meta' => [
                    'provider' => $validated_params['provider'],
                    'model' => $validated_params['model'],
                    'conversation_uuid' => $conversation_uuid,
                ],
                'idempotency_key' => sha1(implode('|', [
                    'content.generated',
                    'direct_standard',
                    $conversation_uuid,
                    $final_title,
                    $validated_params['provider'],
                    $validated_params['model'],
                ])),
            ]
        );
    }

    wp_send_json_success(array_merge([
        'content' => $content,
        'usage' => $usage,
        'meta_description' => $meta_description,
        'focus_keyword' => $focus_keyword,
        'excerpt' => $excerpt,
    'tags' => $tags,
    'conversation_uuid' => $conversation_uuid,
        'image_data' => null // Non-streaming doesn't generate images for now.
    ], smart_seo_keyword_resolution_response_fields_logic($smart_seo_keyword_resolution)));
}
