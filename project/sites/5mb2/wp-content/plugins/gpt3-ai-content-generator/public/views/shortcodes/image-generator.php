<?php

/**
 * Partial View: Frontend Image Generator Shortcode UI
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

use WPAICG\AIPKit_Providers;

// Variables passed from the shortcode class: $nonce,
// $show_provider, $show_model,
// $preset_provider, $preset_model, $preset_size, $preset_number,
// $final_provider, $final_model, $final_size, $final_number,
// $theme, $show_history, $image_history_html,
// $allowed_models,
// $mode, $default_mode, $show_mode_switch,
// $ui_text

$openai_models_display = [];
// Build Google models dynamically from synced option
$google_models_display = [ 'image' => [], 'video' => [] ];
$openrouter_models_display = [];
$xai_models_display = [];
$replicate_models_display = [];
$azure_models_display = [];
if (class_exists('\\WPAICG\\AIPKit_Providers')) {
    $openai_models = AIPKit_Providers::get_openai_image_models();
    if (!empty($openai_models)) {
        foreach ($openai_models as $mdl) {
            $mid = is_array($mdl) ? ($mdl['id'] ?? null) : (is_string($mdl) ? $mdl : null);
            $mname = is_array($mdl) ? ($mdl['name'] ?? $mid) : $mid;
            if ($mid) {
                $openai_models_display[$mid] = $mname;
            }
        }
    }
    $google_image_models = AIPKit_Providers::get_google_image_models();
    if (!empty($google_image_models)) {
        foreach ($google_image_models as $mdl) {
            $mid = is_array($mdl) ? ($mdl['id'] ?? null) : (is_string($mdl) ? $mdl : null);
            $mname = is_array($mdl) ? ($mdl['name'] ?? $mid) : $mid;
            if ($mid) {
                $google_models_display['image'][$mid] = $mname;
            }
        }
    }
    $google_video_models = AIPKit_Providers::get_google_video_models();
    if (!empty($google_video_models)) {
        foreach ($google_video_models as $mdl) {
            $mid = is_array($mdl) ? ($mdl['id'] ?? null) : (is_string($mdl) ? $mdl : null);
            $mname = is_array($mdl) ? ($mdl['name'] ?? $mid) : $mid;
            if ($mid) {
                $google_models_display['video'][$mid] = $mname;
            }
        }
    }

    $openrouter_models = AIPKit_Providers::get_openrouter_image_models();
    if (!empty($openrouter_models)) {
        foreach ($openrouter_models as $mdl) {
            $mid = is_array($mdl) ? ($mdl['id'] ?? null) : (is_string($mdl) ? $mdl : null);
            $mname = is_array($mdl) ? ($mdl['name'] ?? $mid) : $mid;
            if ($mid) {
                $openrouter_models_display[$mid] = $mname;
            }
        }
    }

    $xai_models = AIPKit_Providers::get_xai_image_models();
    if (!empty($xai_models)) {
        foreach ($xai_models as $mdl) {
            $mid = is_array($mdl) ? ($mdl['id'] ?? null) : (is_string($mdl) ? $mdl : null);
            $mname = is_array($mdl) ? ($mdl['name'] ?? $mid) : $mid;
            if ($mid) {
                $xai_models_display[$mid] = $mname;
            }
        }
    }

    $replicate_models = AIPKit_Providers::get_replicate_models();
    if (!empty($replicate_models)) {
        foreach ($replicate_models as $mdl) {
            $mid = is_array($mdl) ? ($mdl['id'] ?? null) : (is_string($mdl) ? $mdl : null);
            $mname = is_array($mdl) ? ($mdl['name'] ?? $mid) : $mid;
            if ($mid) {
                $replicate_models_display[$mid] = $mname;
            }
        }
    }

    $azure_models_list = AIPKit_Providers::get_azure_image_models();
    if (!empty($azure_models_list)) {
        foreach ($azure_models_list as $mdl) {
            $mid = is_array($mdl) ? ($mdl['id'] ?? null) : (is_string($mdl) ? $mdl : null);
            $mname = is_array($mdl) ? ($mdl['name'] ?? $mid) : $mid;
            if ($mid) {
                $azure_models_display[$mid] = $mname;
            }
        }
    }
}

$theme_class = 'aipkit-theme-' . esc_attr($theme);
$allowed_modes = ['generate', 'edit', 'both'];
$shortcode_mode = isset($mode) ? sanitize_key((string) $mode) : 'generate';
if (!in_array($shortcode_mode, $allowed_modes, true)) {
    $shortcode_mode = 'generate';
}

$allowed_default_modes = ['generate', 'edit'];
$shortcode_default_mode = isset($default_mode) ? sanitize_key((string) $default_mode) : 'generate';
if (!in_array($shortcode_default_mode, $allowed_default_modes, true)) {
    $shortcode_default_mode = 'generate';
}

$allow_mode_switch = ($shortcode_mode === 'both' && !empty($show_mode_switch));
$current_image_mode = $shortcode_mode === 'both' ? $shortcode_default_mode : $shortcode_mode;
if (!in_array($current_image_mode, $allowed_default_modes, true)) {
    $current_image_mode = 'generate';
}

$ui_text_settings = isset($ui_text) && is_array($ui_text) ? $ui_text : [];
$get_ui_text = static function (string $key, string $default) use ($ui_text_settings): string {
    if (!isset($ui_text_settings[$key])) {
        return $default;
    }

    $value = sanitize_text_field((string) $ui_text_settings[$key]);
    return $value !== '' ? $value : $default;
};

$generate_label = $get_ui_text('generate_label', __('Generate', 'gpt3-ai-content-generator'));
$edit_label = $get_ui_text('edit_label', __('Edit Image', 'gpt3-ai-content-generator'));
$mode_generate_label = $get_ui_text('mode_generate_label', __('Generate', 'gpt3-ai-content-generator'));
$mode_edit_label = $get_ui_text('mode_edit_label', __('Edit', 'gpt3-ai-content-generator'));
$generate_placeholder = $get_ui_text('generate_placeholder', __('Describe the image you want to generate...', 'gpt3-ai-content-generator'));
$edit_placeholder = $get_ui_text('edit_placeholder', __('Describe how you want to edit the uploaded image...', 'gpt3-ai-content-generator'));
$source_image_label = $get_ui_text('source_image_label', __('Source image', 'gpt3-ai-content-generator'));
$upload_dropzone_title = $get_ui_text('upload_dropzone_title', __('Drop image here or click to upload', 'gpt3-ai-content-generator'));
$upload_dropzone_meta = $get_ui_text('upload_dropzone_meta', __('JPG, PNG, WEBP, GIF up to 10MB', 'gpt3-ai-content-generator'));
$upload_hint = $get_ui_text('upload_hint', __('Upload an image (JPG, PNG, WEBP, GIF up to 10MB), then describe the edits in the prompt.', 'gpt3-ai-content-generator'));
$xai_upload_dropzone_meta = __('JPG or PNG up to 10MB', 'gpt3-ai-content-generator');
$xai_upload_hint = __('xAI image editing supports JPG and PNG source images only.', 'gpt3-ai-content-generator');
$history_title = $get_ui_text('history_title', __('Your Images', 'gpt3-ai-content-generator'));
$initial_prompt_placeholder = $current_image_mode === 'edit' ? $edit_placeholder : $generate_placeholder;
$initial_action_label = $current_image_mode === 'edit' ? $edit_label : $generate_label;

?>
<div
    class="aipkit_shortcode_container aipkit_image_generator_public_wrapper <?php echo esc_attr($theme_class); ?>"
    id="aipkit_public_image_generator"
    data-allowed-models="<?php echo esc_attr($allowed_models); ?>"
    data-image-mode="<?php echo esc_attr($shortcode_mode); ?>"
    data-image-default-mode="<?php echo esc_attr($shortcode_default_mode); ?>"
    data-image-show-mode-switch="<?php echo $allow_mode_switch ? '1' : '0'; ?>"
    data-generate-placeholder="<?php echo esc_attr($generate_placeholder); ?>"
    data-edit-placeholder="<?php echo esc_attr($edit_placeholder); ?>"
    data-generate-label="<?php echo esc_attr($generate_label); ?>"
    data-edit-label="<?php echo esc_attr($edit_label); ?>"
    data-edit-upload-required="<?php echo esc_attr(__('Please upload an image to edit.', 'gpt3-ai-content-generator')); ?>"
    data-edit-provider-unsupported="<?php echo esc_attr(__('Image editing is currently supported only for Google, OpenAI, OpenRouter, and xAI providers.', 'gpt3-ai-content-generator')); ?>"
    data-edit-model-unsupported="<?php echo esc_attr(__('Selected model does not support image editing.', 'gpt3-ai-content-generator')); ?>"
    data-edit-upload-meta-default="<?php echo esc_attr($upload_dropzone_meta); ?>"
    data-edit-upload-hint-default="<?php echo esc_attr($upload_hint); ?>"
    data-edit-upload-meta-xai="<?php echo esc_attr($xai_upload_dropzone_meta); ?>"
    data-edit-upload-hint-xai="<?php echo esc_attr($xai_upload_hint); ?>"
>
    <div class="aipkit_shortcode_body">
        <div class="aipkit_image_generator_input_bar">
            <?php if ($shortcode_mode === 'both') : ?>
                <div class="aipkit_image_generator_mode_switch" <?php echo $allow_mode_switch ? '' : 'hidden'; ?>>
                    <button
                        type="button"
                        class="aipkit_image_generator_mode_btn <?php echo $current_image_mode === 'generate' ? 'is-active' : ''; ?>"
                        data-aipkit-image-mode="generate"
                        aria-pressed="<?php echo $current_image_mode === 'generate' ? 'true' : 'false'; ?>"
                    >
                        <?php echo esc_html($mode_generate_label); ?>
                    </button>
                    <button
                        type="button"
                        class="aipkit_image_generator_mode_btn <?php echo $current_image_mode === 'edit' ? 'is-active' : ''; ?>"
                        data-aipkit-image-mode="edit"
                        aria-pressed="<?php echo $current_image_mode === 'edit' ? 'true' : 'false'; ?>"
                    >
                        <?php echo esc_html($mode_edit_label); ?>
                    </button>
                </div>
            <?php endif; ?>

            <input type="hidden" id="aipkit_public_image_mode" name="image_mode" value="<?php echo esc_attr($current_image_mode); ?>">

            <div class="aipkit_form-group aipkit_image_generator_prompt_area">
                <textarea
                    id="aipkit_public_image_prompt"
                    name="image_prompt"
                    class="aipkit_form-input aipkit_image_prompt_textarea"
                    rows="3"
                    placeholder="<?php echo esc_attr($initial_prompt_placeholder); ?>"
                ></textarea>
            </div>
            <div
                class="aipkit_form-group aipkit_image_generator_edit_upload_row"
                id="aipkit_public_image_edit_upload_row"
                <?php echo $current_image_mode === 'edit' ? '' : 'hidden'; ?>
            >
                <label class="aipkit_form-label" for="aipkit_public_image_edit_source_file">
                    <?php echo esc_html($source_image_label); ?>
                </label>
                <div
                    id="aipkit_public_image_edit_dropzone"
                    class="aipkit_image_edit_dropzone"
                    role="button"
                    tabindex="0"
                    aria-controls="aipkit_public_image_edit_source_file"
                    aria-describedby="aipkit_public_image_edit_upload_hint"
                >
                    <span class="aipkit_image_edit_dropzone_title">
                        <?php echo esc_html($upload_dropzone_title); ?>
                    </span>
                    <span class="aipkit_image_edit_dropzone_meta">
                        <?php echo esc_html($upload_dropzone_meta); ?>
                    </span>
                </div>
                <input
                    type="file"
                    id="aipkit_public_image_edit_source_file"
                    name="source_image"
                    class="aipkit_form-input aipkit_image_edit_source_input"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                >
                <div
                    id="aipkit_public_image_edit_file_summary"
                    class="aipkit_image_edit_file_summary"
                    hidden
                >
                    <img
                        id="aipkit_public_image_edit_file_preview"
                        class="aipkit_image_edit_file_preview"
                        alt="<?php esc_attr_e('Selected source image preview', 'gpt3-ai-content-generator'); ?>"
                        hidden
                    >
                    <div class="aipkit_image_edit_file_details">
                        <span
                            id="aipkit_public_image_edit_file_name"
                            class="aipkit_image_edit_file_name"
                        ></span>
                    </div>
                    <button
                        type="button"
                        id="aipkit_public_image_edit_file_remove"
                        class="aipkit_image_edit_file_remove"
                    >
                        <?php esc_html_e('Remove', 'gpt3-ai-content-generator'); ?>
                    </button>
                </div>
                <span class="aipkit_image_generator_edit_upload_hint">
                    <?php echo esc_html($upload_hint); ?>
                </span>
                <span
                    id="aipkit_public_image_edit_upload_feedback"
                    class="aipkit_image_edit_upload_feedback"
                    hidden
                ></span>
            </div>
            <div
                id="aipkit_public_image_edit_mode_notice"
                class="aipkit_image_generator_edit_mode_notice"
                hidden
            ></div>
             <div class="aipkit_image_generator_controls_row">
                <div class="aipkit_image_generator_options">
                    <?php if ($show_provider) : ?>
                        <div class="aipkit_form-group aipkit_image_gen_option">
                            <label class="aipkit_form-label" for="aipkit_public_image_provider"><?php esc_html_e('Provider', 'gpt3-ai-content-generator'); ?></label>
                            <select id="aipkit_public_image_provider" name="image_provider" class="aipkit_form-input" data-aipkit-provider-notice-target="aipkit_provider_notice_image_generator">
                                <?php
                                $all_providers = ['OpenAI', 'Google', 'OpenRouter', 'xAI', 'Azure', 'Replicate'];
                                $allowed_models_arr = !empty($allowed_models) ? array_map('trim', explode(',', strtolower($allowed_models))) : [];
                                if (!empty($allowed_models_arr)) {
                                    $derived = [];
                                    $openai_lookup = array_flip(array_map('strtolower', array_keys($openai_models_display)));
                                    $google_lookup = [];
                                    foreach ($google_models_display as $type => $models_array) {
                                        foreach ($models_array as $id => $name) {
                                            $google_lookup[strtolower((string) $id)] = true;
                                        }
                                    }
                                    $openrouter_lookup = array_flip(array_map('strtolower', array_keys($openrouter_models_display)));
                                    $xai_lookup = array_flip(array_map('strtolower', array_keys($xai_models_display)));
                                    $azure_lookup = array_flip(array_map('strtolower', array_keys($azure_models_display)));
                                    $replicate_lookup = array_flip(array_map('strtolower', array_keys($replicate_models_display)));
                                    foreach ($allowed_models_arr as $mid) {
                                        if (isset($openai_lookup[$mid])) {
                                            $derived['OpenAI'] = true;
                                        } elseif (isset($google_lookup[$mid])) {
                                            $derived['Google'] = true;
                                        } elseif (isset($openrouter_lookup[$mid])) {
                                            $derived['OpenRouter'] = true;
                                        } elseif (isset($xai_lookup[$mid])) {
                                            $derived['xAI'] = true;
                                        } elseif (isset($azure_lookup[$mid])) {
                                            $derived['Azure'] = true;
                                        } elseif (isset($replicate_lookup[$mid])) {
                                            $derived['Replicate'] = true;
                                        } elseif (strpos($mid, '/') !== false) {
                                            // Fallback for older settings when full model list isn't synced yet.
                                            if (strpos($mid, ':') !== false) {
                                                $derived['Replicate'] = true;
                                            } else {
                                                $derived['OpenRouter'] = true;
                                            }
                                        }
                                    }
                                    $providers_to_show = array_values(array_intersect($all_providers, array_keys($derived)));
                                    if (empty($providers_to_show)) {
                                        $providers_to_show = $all_providers; // fallback safety
                                    }
                                } else {
                                    $providers_to_show = $all_providers;
                                }
                                foreach ($providers_to_show as $provider_name) : ?>
                                    <option value="<?php echo esc_attr($provider_name); ?>" <?php selected($final_provider, $provider_name); ?>><?php echo esc_html($provider_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else : ?>
                        <input type="hidden" name="image_provider" value="<?php echo esc_attr($final_provider); ?>">
                    <?php endif; ?>

                    <?php if ($show_model) : ?>
                        <div class="aipkit_form-group aipkit_image_gen_option">
                            <label class="aipkit_form-label" for="aipkit_public_image_model"><?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?></label>
                            <select id="aipkit_public_image_model" name="image_model" class="aipkit_form-input">
                                 <?php // Options populated by JS, but set selected based on final_model?>
                                 <?php if ($final_provider === 'OpenAI'): ?>
                                    <?php
                            // Sort OpenAI models for display with the current default first.
                            $sorted_openai_keys_render = ['gpt-image-2', 'gpt-image-1.5', 'gpt-image-1', 'gpt-image-1-mini'];
                                     $final_openai_models_render = [];
                                     foreach ($sorted_openai_keys_render as $key) {
                                         if (isset($openai_models_display[$key])) {
                                             $final_openai_models_render[$key] = $openai_models_display[$key];
                                         }
                                     }
                                     // Add any other OpenAI models not in the sort list (future-proofing)
                                     foreach ($openai_models_display as $id => $name) {
                                         if (!isset($final_openai_models_render[$id])) {
                                             $final_openai_models_render[$id] = $name;
                                         }
                                     }
                                     ?>
                                    <?php foreach ($final_openai_models_render as $id => $name): ?>
                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($final_model, $id); ?>>
                                            <?php echo esc_html($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                     <?php if ($final_model && !array_key_exists($final_model, $openai_models_display)) : ?>
                                        <option value="<?php echo esc_attr($final_model); ?>" selected><?php echo esc_html($final_model); ?> (Manual)</option>
                                     <?php endif; ?>
                                 <?php elseif ($final_provider === 'Google'): ?>
                                     <?php foreach ($google_models_display as $type => $models_array): ?>
                                        <optgroup label="<?php echo esc_attr(ucfirst($type) . ' Models'); ?>">
                                            <?php foreach ($models_array as $id => $name): ?>
                                                <option value="<?php echo esc_attr($id); ?>" <?php selected($final_model, $id); ?>>
                                                    <?php echo esc_html($name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                     <?php endforeach; ?>
                                     <?php 
                                     // Check if final_model exists in any of the optgroups
                                     $model_found = false;
                                     foreach ($google_models_display as $type => $models_array) {
                                         if (array_key_exists($final_model, $models_array)) {
                                             $model_found = true;
                                             break;
                                         }
                                     }
                                     if ($final_model && !$model_found) : ?>
                                        <option value="<?php echo esc_attr($final_model); ?>" selected><?php echo esc_html($final_model); ?> (Manual)</option>
                                     <?php endif; ?>
                                 <?php elseif ($final_provider === 'OpenRouter'): ?>
                                     <?php foreach ($openrouter_models_display as $id => $name): ?>
                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($final_model, $id); ?>>
                                            <?php echo esc_html($name); ?>
                                        </option>
                                     <?php endforeach; ?>
                                     <?php if ($final_model && !array_key_exists($final_model, $openrouter_models_display)) : ?>
                                        <option value="<?php echo esc_attr($final_model); ?>" selected><?php echo esc_html($final_model); ?> (Manual)</option>
                                     <?php endif; ?>
                                 <?php elseif ($final_provider === 'xAI'): ?>
                                     <?php foreach ($xai_models_display as $id => $name): ?>
                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($final_model, $id); ?>>
                                            <?php echo esc_html($name); ?>
                                        </option>
                                     <?php endforeach; ?>
                                     <?php if ($final_model && !array_key_exists($final_model, $xai_models_display)) : ?>
                                        <option value="<?php echo esc_attr($final_model); ?>" selected><?php echo esc_html($final_model); ?> (Manual)</option>
                                     <?php endif; ?>
                                 <?php elseif ($final_provider === 'Azure'): ?>
                                     <?php 
                                     // Azure models handling
                                     $azure_models_display_rows = AIPKit_Providers::get_azure_image_models();
                                     ?>
                                     <?php foreach ($azure_models_display_rows as $model): ?>
                                        <option value="<?php echo esc_attr($model['id']); ?>" <?php selected($final_model, $model['id']); ?>>
                                            <?php echo esc_html($model['name']); ?>
                                        </option>
                                     <?php endforeach; ?>
                                     <?php 
                                     // Check if final_model exists in azure models
                                     $model_found = false;
                                     foreach ($azure_models_display_rows as $model) {
                                         if ($model['id'] === $final_model) {
                                             $model_found = true;
                                             break;
                                         }
                                     }
                                     if ($final_model && !$model_found) : ?>
                                        <option value="<?php echo esc_attr($final_model); ?>" selected><?php echo esc_html($final_model); ?> (Manual)</option>
                                     <?php endif; ?>
                                 <?php else: ?>
                                     <option value=""><?php esc_html_e('(Select Provider)', 'gpt3-ai-content-generator'); ?></option>
                                 <?php endif; ?>
                            </select>
                        </div>
                     <?php else : ?>
                        <input type="hidden" name="image_model" value="<?php echo esc_attr($final_model); ?>">
                    <?php endif; ?>
                </div>
                <div class="aipkit_image_generator_action_area">
                    <button id="aipkit_public_generate_image_btn" class="aipkit_btn aipkit_btn-primary aipkit_image_generate_btn">
                        <span class="dashicons dashicons-images-alt"></span>
                        <span class="aipkit_btn-text"><?php echo esc_html($initial_action_label); ?></span>
                        <span class="aipkit_spinner" hidden></span>
                    </button>
                </div>
             </div>
        </div>
        <div class="aipkit_image_generator_results" id="aipkit_public_image_results">
             <p class="aipkit_image_results_placeholder aipkit_image_results_placeholder--centered aipkit_image_results_placeholder--italic"></p>
        </div>
        <input type="hidden" id="aipkit_image_generator_public_nonce" value="<?php echo esc_attr($nonce); ?>">

        <?php if (isset($show_history) && $show_history && is_user_logged_in() && !empty(trim($image_history_html))): ?>
            <div class="aipkit_image_history_section">
                <h3 class="aipkit_image_history_title"><?php echo esc_html($history_title); ?></h3>
                <?php
                // We're keeping the HTML generation in PHP for initial load, JS handles deletion.
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is generated in the shortcode class.
                echo $image_history_html;
?>
            </div>
        <?php endif; ?>
    </div>
</div>
