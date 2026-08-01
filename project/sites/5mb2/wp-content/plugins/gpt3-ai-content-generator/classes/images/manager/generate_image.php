<?php


namespace WPAICG\Images\Manager;

use WPAICG\Images\AIPKit_Image_Manager;
use WPAICG\Images\AIPKit_Image_Provider_Strategy_Factory;
use WPAICG\AIPKit_Providers;
use WPAICG\Images\AIPKit_Image_Storage_Helper;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return mixed[]|\WP_Error
 */
function generate_image_logic(AIPKit_Image_Manager $managerInstance, string $prompt, array $options = [], ?int $wp_user_id = null)
{
    $prompt = AIPKit_Prompt_Sanitizer::sanitize($prompt);
    $provider_raw = $options['provider'] ?? 'openai';

    $provider_normalized = AIPKit_Providers::normalize_provider_label((string) $provider_raw);
    if (!in_array($provider_normalized, ['OpenAI', 'OpenRouter', 'Azure', 'Google', 'xAI', 'Pexels', 'Pixabay', 'Replicate'], true)) {
        $provider_normalized = 'OpenAI';
    }

    $all_settings = $managerInstance->get_image_settings();
    $provider_module_defaults = $all_settings['defaults'][$provider_normalized] ?? ($all_settings['defaults']['OpenAI'] ?? []);
    $final_options = array_merge($provider_module_defaults, $options);
    $final_options['provider'] = $provider_normalized;

    $api_params = AIPKit_Providers::get_provider_data($provider_normalized);
    if (empty($api_params['api_key'])) {
        /* translators: %s: The provider name that was attempted to be used for image generation. */
        return new WP_Error('missing_api_key', sprintf(__('%s API Key is missing.', 'gpt3-ai-content-generator'), $provider_normalized), ['status' => 400]);
    }
    if ($provider_normalized === 'Azure' && empty($api_params['endpoint'])) {
        return new WP_Error('missing_azure_endpoint', __('Azure Endpoint/Region URL is required for image generation.', 'gpt3-ai-content-generator'), ['status' => 400]);
    }

    $strategy = AIPKit_Image_Provider_Strategy_Factory::get_strategy($provider_normalized);
    if (is_wp_error($strategy)) {
        return $strategy;
    }

    if (empty($final_options['model']) && $provider_normalized === 'OpenAI') {
        $final_options['model'] = AIPKit_Providers::get_default_openai_image_model();
    } elseif (empty($final_options['model']) && $provider_normalized === 'OpenRouter') {
        $openrouter_image_models = class_exists(AIPKit_Providers::class) ? AIPKit_Providers::get_openrouter_image_models() : [];
        $first_openrouter_model = '';
        if (is_array($openrouter_image_models) && !empty($openrouter_image_models)) {
            foreach ($openrouter_image_models as $openrouter_model) {
                if (!is_array($openrouter_model) || empty($openrouter_model['id'])) {
                    continue;
                }
                $candidate_id = sanitize_text_field((string) $openrouter_model['id']);
                if ($candidate_id === '' || in_array($candidate_id, ['openrouter/auto', 'auto'], true)) {
                    continue;
                }
                $first_openrouter_model = $candidate_id;
                break;
            }
        }
        $final_options['model'] = $first_openrouter_model !== '' ? $first_openrouter_model : 'google/gemini-2.5-flash-image-preview';
    } elseif (empty($final_options['model']) && $provider_normalized === 'Google') {
        $final_options['model'] = AIPKit_Providers::get_default_google_image_model();
    } elseif (empty($final_options['model']) && $provider_normalized === 'xAI') {
        $final_options['model'] = AIPKit_Providers::get_default_xai_image_model();
    }
    if ($provider_normalized === 'OpenAI') {
        $final_options['model'] = AIPKit_Providers::normalize_openai_image_model(
            isset($final_options['model']) ? (string) $final_options['model'] : null
        );
    }
    if (empty($final_options['size'])) {
        $final_options['size'] = '1024x1024';
    }
    if (empty($final_options['n'])) {
        $final_options['n'] = 1;
    }
    if ($provider_normalized === 'xAI') {
        $final_options['model'] = AIPKit_Providers::normalize_xai_image_model(
            isset($final_options['model']) ? (string) $final_options['model'] : null
        );
        if (!isset($final_options['response_format'])) {
            $final_options['response_format'] = 'b64_json';
        }
    }

    if (
        $provider_normalized === 'OpenAI'
        && AIPKit_Providers::is_openai_gpt_image_model((string) ($final_options['model'] ?? ''))
    ) {
        if (isset($final_options['response_format']) && !isset($final_options['output_format'])) {
            if ($final_options['response_format'] === 'b64_json') {
                $final_options['output_format'] = 'png';
            }
            unset($final_options['response_format']);
        }
    }

    $result_from_strategy = $strategy->generate_image($prompt, $api_params, $final_options);

    if (is_wp_error($result_from_strategy)) {
        return $result_from_strategy;
    }

    if ($wp_user_id !== null && class_exists(AIPKit_Image_Storage_Helper::class) && isset($result_from_strategy['images'])) {
        $saved_image_data = [];
        $meta_list = isset($final_options['aipkit_attachment_meta_list']) && is_array($final_options['aipkit_attachment_meta_list'])
            ? array_values($final_options['aipkit_attachment_meta_list'])
            : [];
        foreach ($result_from_strategy['images'] as $index => $image_item) {
            $image_options = $final_options;
            if (!empty($meta_list[$index]) && is_array($meta_list[$index])) {
                $image_options['aipkit_attachment_meta'] = $meta_list[$index];
            }
            $attachment_id_or_error = AIPKit_Image_Storage_Helper::save_image_to_media_library(
                $image_item,
                $prompt,
                $image_options,
                $wp_user_id
            );
            if (!is_wp_error($attachment_id_or_error) && $attachment_id_or_error) {
                $image_item['attachment_id'] = $attachment_id_or_error;
                $image_item['media_library_url'] = wp_get_attachment_url($attachment_id_or_error);
            }
            $saved_image_data[] = $image_item;
        }
        $result_from_strategy['images'] = $saved_image_data;
    }

    $managerInstance->emit_generated_event($prompt, $result_from_strategy, $final_options, $wp_user_id);

    return $result_from_strategy;
}
