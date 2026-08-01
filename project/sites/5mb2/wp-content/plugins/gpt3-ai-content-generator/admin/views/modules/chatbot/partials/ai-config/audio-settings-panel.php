<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.
$stt_model_count = (isset($openai_stt_models) && is_array($openai_stt_models)) ? count($openai_stt_models) : 0;
$hide_stt_controls = $stt_model_count <= 1;
$stt_provider_options = [
    'OpenAI' => __('OpenAI', 'gpt3-ai-content-generator'),
];
$hide_stt_provider_field = count($stt_provider_options) <= 1;
$selected_stt_provider_for_ui = array_key_exists((string) $stt_provider, $stt_provider_options)
    ? (string) $stt_provider
    : 'OpenAI';
?>
<div class="aipkit_popover_options_list">
    <div class="aipkit_popover_option_group aipkit_audio_feature_group aipkit_audio_feature_group--stt">
        <div class="aipkit_popover_option_row aipkit_audio_toggle_row aipkit_audio_toggle_row--stt">
            <div class="aipkit_popover_option_main">
                <label
                    class="aipkit_popover_option_label"
                    for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_enable_voice_input_sheet"

                >
                    <?php esc_html_e('Speech to text', 'gpt3-ai-content-generator'); ?>
                </label>
                <div class="aipkit_popover_option_actions">
                    <select
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_enable_voice_input_sheet"
                        name="enable_voice_input"
                        class="aipkit_popover_option_select aipkit_popover_option_select--compact aipkit_voice_input_toggle_switch"
                    >
                        <option value="1" <?php selected($enable_voice_input, '1'); ?>><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                        <option value="0" <?php selected($enable_voice_input, '0'); ?>><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                    </select>
                </div>
            </div>
        </div>
        <div
            class="aipkit_popover_option_row aipkit_stt_provider_row"
            data-stt-controls-hidden="<?php echo $hide_stt_controls ? '1' : '0'; ?>"
            data-stt-default-provider="OpenAI"
            data-stt-default-model="whisper-1"
            style="display: <?php echo ($enable_voice_input === '1' && !$hide_stt_controls) ? 'block' : 'none'; ?>;"
        >
            <div class="aipkit_popover_option_main">
                <div class="aipkit_audio_settings_grid aipkit_audio_settings_grid--stt aipkit_stt_provider_conditional_row" style="display: <?php echo ($enable_voice_input === '1' && !$hide_stt_controls) ? 'grid' : 'none'; ?>;">
                    <div class="aipkit_audio_settings_field aipkit_audio_settings_field--provider<?php echo $hide_stt_provider_field ? ' aipkit_audio_settings_field--hidden' : ''; ?>">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_stt_provider_sheet"
                        >
                            <?php esc_html_e('Provider', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <select
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_stt_provider_sheet"
                            name="stt_provider"
                            class="aipkit_popover_option_select aipkit_popover_option_select--compact aipkit_stt_provider_select"
                        >
                            <?php foreach ($stt_provider_options as $stt_provider_key => $stt_provider_label) : ?>
                                <option value="<?php echo esc_attr($stt_provider_key); ?>" <?php selected($selected_stt_provider_for_ui, $stt_provider_key); ?>>
                                    <?php echo esc_html($stt_provider_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="aipkit_audio_settings_field aipkit_audio_settings_field--model<?php echo $hide_stt_provider_field ? ' aipkit_audio_settings_field--wide' : ''; ?> aipkit_stt_model_field" data-stt-provider="OpenAI" style="display: <?php echo $selected_stt_provider_for_ui === 'OpenAI' ? 'flex' : 'none'; ?>;">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_stt_openai_model_id_sheet"
                        >
                            <?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <select
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_stt_openai_model_id_sheet"
                            name="stt_openai_model_id"
                            class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                        >
                            <?php
                            $found_current_stt = false;
                            if (!empty($openai_stt_models)) {
                                foreach ($openai_stt_models as $model) {
                                    $model_id_val = $model['id'] ?? '';
                                    $model_name_val = $model['name'] ?? $model_id_val;
                                    if ($model_id_val === $stt_openai_model_id) {
                                        $found_current_stt = true;
                                    }
                                    echo '<option value="' . esc_attr($model_id_val) . '" ' . selected($stt_openai_model_id, $model_id_val, false) . '>' . esc_html($model_name_val) . '</option>';
                                }
                            }
                            if (!$found_current_stt && !empty($stt_openai_model_id)) {
                                echo '<option value="' . esc_attr($stt_openai_model_id) . '" selected>' . esc_html($stt_openai_model_id) . '</option>';
                            } elseif (empty($openai_stt_models) && empty($stt_openai_model_id)) {
                                echo '<option value="whisper-1" selected>whisper-1 (Default)</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="aipkit_popover_option_group aipkit_audio_feature_group aipkit_audio_feature_group--tts">
            <div class="aipkit_popover_option_row aipkit_audio_toggle_row aipkit_audio_toggle_row--tts">
                <div class="aipkit_popover_option_main">
                    <label
                        class="aipkit_popover_option_label"
                        for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_enabled_sheet"

                    >
                        <?php esc_html_e('Text to speech', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <div class="aipkit_popover_option_actions">
                        <select
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_enabled_sheet"
                            name="tts_enabled"
                            class="aipkit_popover_option_select aipkit_popover_option_select--compact aipkit_tts_toggle_switch"
                        >
                            <option value="1" <?php selected($tts_enabled, '1'); ?>><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                            <option value="0" <?php selected($tts_enabled, '0'); ?>><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="aipkit_popover_option_row aipkit_tts_provider_row aipkit_tts_settings_row" style="display: <?php echo $tts_enabled === '1' ? 'block' : 'none'; ?>;">
                <div class="aipkit_popover_option_main">
                    <div class="aipkit_audio_settings_grid aipkit_audio_settings_grid--tts aipkit_tts_conditional_settings" style="display: <?php echo $tts_enabled === '1' ? 'grid' : 'none'; ?>;">
                        <div class="aipkit_audio_settings_field aipkit_audio_settings_field--provider">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_provider_sheet"
                            >
                                <?php esc_html_e('Provider', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <select
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_provider_sheet"
                                name="tts_provider"
                                class="aipkit_popover_option_select aipkit_popover_option_select--compact aipkit_tts_provider_select"
                            >
                                <?php foreach ($tts_providers as $provider_name): ?>
                                    <option value="<?php echo esc_attr($provider_name); ?>" <?php selected($tts_provider, $provider_name); ?>><?php echo esc_html($provider_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="aipkit_audio_settings_field aipkit_tts_auto_play_container">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_auto_play_sheet"
                            >
                                <?php esc_html_e('Auto play', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <select
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_auto_play_sheet"
                                name="tts_auto_play"
                                class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                            >
                                <option value="1" <?php selected($tts_auto_play, '1'); ?>><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                                <option value="0" <?php selected($tts_auto_play, '0'); ?>><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                            </select>
                        </div>
                        <div class="aipkit_audio_settings_field aipkit_audio_settings_field--wide aipkit_tts_field aipkit_tts_google_voice_row" data-provider="Google" style="display: <?php echo ($tts_enabled === '1' && $tts_provider === 'Google') ? 'flex' : 'none'; ?>;">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_google_voice_id_sheet"
                            >
                                <?php esc_html_e('Voice', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <select
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_google_voice_id_sheet"
                                name="tts_google_voice_id"
                                class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                            >
                                <option value=""><?php esc_html_e('-- Select Voice --', 'gpt3-ai-content-generator'); ?></option>
                                <?php
                                if (!empty($google_tts_voices) && is_array($google_tts_voices)) {
                                    $voices_by_lang = [];
                                    foreach ($google_tts_voices as $voice) {
                                        if (!isset($voice['id'], $voice['name'], $voice['languageCodes'][0])) {
                                            continue;
                                        }
                                        $lang_code = $voice['languageCodes'][0];
                                        if (!isset($voices_by_lang[$lang_code])) {
                                            $voices_by_lang[$lang_code] = [];
                                        }
                                        $voices_by_lang[$lang_code][] = $voice;
                                    }
                                    ksort($voices_by_lang);
                                    foreach ($voices_by_lang as $lang_code => $voices) {
                                        $lang_name = $lang_code;
                                        if (class_exists('IntlDisplayNames')) {
                                            try {
                                                $lang_name = \IntlDisplayNames::forLanguageTag($lang_code, 'en');
                                            } catch (\Exception $e) {
                                                $lang_name = $lang_code;
                                            }
                                        }
                                        echo '<optgroup label="' . esc_attr("{$lang_name} ({$lang_code})") . '">';
                                        usort($voices, fn($a, $b) => strcmp($a['name'], $b['name']));
                                        foreach ($voices as $voice) {
                                            echo '<option value="' . esc_attr($voice['id']) . '" ' . selected($tts_google_voice_id, $voice['id'], false) . '>' . esc_html($voice['name']) . '</option>';
                                        }
                                        echo '</optgroup>';
                                    }
                                } elseif (!empty($tts_google_voice_id)) {
                                    echo '<option value="' . esc_attr($tts_google_voice_id) . '" selected>' . esc_html($tts_google_voice_id) . ' (Saved)</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="aipkit_audio_settings_field aipkit_tts_field aipkit_tts_openai_voice_row" data-provider="OpenAI" style="display: <?php echo ($tts_enabled === '1' && $tts_provider === 'OpenAI') ? 'flex' : 'none'; ?>;">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_openai_voice_id_sheet"
                            >
                                <?php esc_html_e('Voice', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <select
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_openai_voice_id_sheet"
                                name="tts_openai_voice_id"
                                class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                            >
                                <?php foreach ($openai_tts_voices as $voice): ?>
                                    <option value="<?php echo esc_attr($voice['id']); ?>" <?php selected($tts_openai_voice_id, $voice['id']); ?>><?php echo esc_html($voice['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="aipkit_audio_settings_field aipkit_tts_field aipkit_tts_openai_model_row" data-provider="OpenAI" style="display: <?php echo ($tts_enabled === '1' && $tts_provider === 'OpenAI') ? 'flex' : 'none'; ?>;">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_openai_model_id_sheet"
                            >
                                <?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <select
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_openai_model_id_sheet"
                                name="tts_openai_model_id"
                                class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                            >
                                <?php
                                if (!empty($openai_tts_models)) {
                                    foreach ($openai_tts_models as $model) {
                                        $model_id_val = $model['id'] ?? '';
                                        $model_name_val = $model['name'] ?? $model_id_val;
                                        echo '<option value="' . esc_attr($model_id_val) . '" ' . selected($tts_openai_model_id, $model_id_val, false) . '>' . esc_html($model_name_val) . '</option>';
                                    }
                                } elseif (!empty($tts_openai_model_id)) {
                                    echo '<option value="' . esc_attr($tts_openai_model_id) . '" selected>' . esc_html($tts_openai_model_id) . ' (Saved)</option>';
                                } else {
                                    echo '<option value="' . esc_attr(\WPAICG\Chat\Storage\BotSettingsManager::DEFAULT_TTS_OPENAI_MODEL_ID) . '" selected>' . esc_html(\WPAICG\Chat\Storage\BotSettingsManager::DEFAULT_TTS_OPENAI_MODEL_ID) . ' (Default)</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="aipkit_audio_settings_field aipkit_tts_field aipkit_tts_elevenlabs_voice_row" data-provider="ElevenLabs" style="display: <?php echo ($tts_enabled === '1' && $tts_provider === 'ElevenLabs') ? 'flex' : 'none'; ?>;">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_elevenlabs_voice_id_sheet"
                            >
                                <?php esc_html_e('Voice', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <select
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_elevenlabs_voice_id_sheet"
                                name="tts_elevenlabs_voice_id"
                                class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                            >
                                <option value=""><?php esc_html_e('-- Select Voice --', 'gpt3-ai-content-generator'); ?></option>
                                <?php
                                if (!empty($elevenlabs_tts_voices) && is_array($elevenlabs_tts_voices)) {
                                    foreach ($elevenlabs_tts_voices as $voice) {
                                        if (!isset($voice['id'], $voice['name'])) {
                                            continue;
                                        }
                                        echo '<option value="' . esc_attr($voice['id']) . '" ' . selected($tts_elevenlabs_voice_id, $voice['id'], false) . '>' . esc_html($voice['name']) . '</option>';
                                    }
                                } elseif (!empty($tts_elevenlabs_voice_id)) {
                                    echo '<option value="' . esc_attr($tts_elevenlabs_voice_id) . '" selected>' . esc_html($tts_elevenlabs_voice_id) . ' (Saved)</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="aipkit_audio_settings_field aipkit_tts_field aipkit_tts_elevenlabs_model_row" data-provider="ElevenLabs" style="display: <?php echo ($tts_enabled === '1' && $tts_provider === 'ElevenLabs') ? 'flex' : 'none'; ?>;">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_elevenlabs_model_id_sheet"
                            >
                                <?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <select
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_tts_elevenlabs_model_id_sheet"
                                name="tts_elevenlabs_model_id"
                                class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                            >
                                <option value=""><?php esc_html_e('-- Select Model (Optional) --', 'gpt3-ai-content-generator'); ?></option>
                                <?php
                                if (!empty($elevenlabs_tts_models) && is_array($elevenlabs_tts_models)) {
                                    foreach ($elevenlabs_tts_models as $model) {
                                        if (!isset($model['id'], $model['name'])) {
                                            continue;
                                        }
                                        echo '<option value="' . esc_attr($model['id']) . '" ' . selected($tts_elevenlabs_model_id, $model['id'], false) . '>' . esc_html($model['name']) . '</option>';
                                    }
                                } elseif (!empty($tts_elevenlabs_model_id)) {
                                    echo '<option value="' . esc_attr($tts_elevenlabs_model_id) . '" selected>' . esc_html($tts_elevenlabs_model_id) . ' (Saved)</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    <div class="aipkit_popover_option_group aipkit_audio_feature_group aipkit_audio_feature_group--realtime">
        <div class="aipkit_popover_option_row aipkit_audio_toggle_row aipkit_audio_toggle_row--realtime">
            <div class="aipkit_popover_option_main">
                <label
                    class="aipkit_popover_option_label"
                    for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_enable_realtime_voice_sheet"
                >
                    <?php esc_html_e('Realtime voice', 'gpt3-ai-content-generator'); ?>
                </label>
                <div class="aipkit_popover_option_actions">
                    <select
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_enable_realtime_voice_sheet"
                        name="enable_realtime_voice"
                        class="aipkit_popover_option_select aipkit_popover_option_select--compact aipkit_enable_realtime_voice_toggle"
                        <?php echo $rt_controls_disabled ? 'disabled' : ''; ?>
                    >
                        <option value="1" <?php selected($enable_realtime_voice, '1'); ?>><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                        <option value="0" <?php selected($enable_realtime_voice, '0'); ?>><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                    </select>
                    <?php if ($rt_disabled_by_plan) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpaicg-pricing')); ?>" class="aipkit_popover_upgrade_link aipkit_pro_upgrade_button" title="<?php esc_attr_e('Upgrade', 'gpt3-ai-content-generator'); ?>"><?php esc_html_e('Upgrade', 'gpt3-ai-content-generator'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div
            class="aipkit_realtime_voice_settings_container"
            style="display: <?php echo $rt_force_visible ? 'block' : (($enable_realtime_voice === '1') ? 'block' : 'none'); ?>;"
            <?php echo $rt_force_visible ? 'data-force-visible="1"' : ''; ?>
        >
            <div class="aipkit_audio_settings_grid aipkit_audio_settings_grid--realtime">
                <div class="aipkit_audio_settings_field aipkit_rt_dependent">
                    <label
                        class="aipkit_popover_option_label"
                        for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_realtime_model_sheet"
                    >
                        <?php esc_html_e('Model', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <select
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_realtime_model_sheet"
                        name="realtime_model"
                        class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                        <?php echo $rt_controls_disabled ? 'disabled' : ''; ?>
                    >
                        <?php foreach ($realtime_models as $model_id): ?>
                            <option value="<?php echo esc_attr($model_id); ?>" <?php selected($realtime_model, $model_id); ?>><?php echo esc_html($model_id); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="aipkit_audio_settings_field aipkit_rt_dependent">
                    <label
                        class="aipkit_popover_option_label"
                        for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_realtime_voice_sheet"
                    >
                        <?php esc_html_e('Voice', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <select
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_realtime_voice_sheet"
                        name="realtime_voice"
                        class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                        <?php echo $rt_controls_disabled ? 'disabled' : ''; ?>
                    >
                        <?php foreach ($realtime_voices as $voice_id): ?>
                            <option value="<?php echo esc_attr($voice_id); ?>" <?php selected($realtime_voice, $voice_id); ?>><?php echo esc_html(ucfirst($voice_id)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="aipkit_audio_settings_field aipkit_rt_dependent">
                    <label
                        class="aipkit_popover_option_label"
                        for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_turn_detection_sheet"
                    >
                        <?php esc_html_e('When to respond', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <select
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_turn_detection_sheet"
                        name="turn_detection"
                        class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                        <?php echo $rt_controls_disabled ? 'disabled' : ''; ?>
                    >
                        <option value="none" <?php selected($turn_detection, 'none'); ?>><?php esc_html_e('Push to talk', 'gpt3-ai-content-generator'); ?></option>
                        <option value="server_vad" <?php selected($turn_detection, 'server_vad'); ?>><?php esc_html_e('Automatic', 'gpt3-ai-content-generator'); ?></option>
                        <option value="semantic_vad" <?php selected($turn_detection, 'semantic_vad'); ?>><?php esc_html_e('Smart', 'gpt3-ai-content-generator'); ?></option>
                    </select>
                </div>

                <div class="aipkit_audio_settings_field aipkit_rt_dependent">
                    <label
                        class="aipkit_popover_option_label"
                        for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_direct_voice_mode_sheet"
                    >
                        <?php esc_html_e('Start with voice', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <select
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_direct_voice_mode_sheet"
                        name="direct_voice_mode"
                        class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                        <?php echo $rt_controls_disabled ? 'disabled' : ''; ?> <?php disabled($direct_voice_mode_disabled); ?>
                    >
                        <option value="1" <?php selected($direct_voice_mode, '1'); ?>><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                        <option value="0" <?php selected($direct_voice_mode, '0'); ?>><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                    </select>
                </div>

                <div class="aipkit_audio_settings_field aipkit_rt_dependent">
                    <label
                        class="aipkit_popover_option_label"
                        for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_input_audio_noise_reduction_sheet"
                    >
                        <?php esc_html_e('Noise reduction', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <select
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_input_audio_noise_reduction_sheet"
                        name="input_audio_noise_reduction"
                        class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                        <?php echo $rt_controls_disabled ? 'disabled' : ''; ?>
                    >
                        <option value="1" <?php selected($input_audio_noise_reduction, '1'); ?>><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                        <option value="0" <?php selected($input_audio_noise_reduction, '0'); ?>><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                    </select>
                </div>

                <div class="aipkit_audio_settings_field aipkit_audio_format_field aipkit_rt_dependent">
                    <label
                        class="aipkit_popover_option_label"
                        for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_audio_format_combo_sheet"
                    >
                        <?php esc_html_e('Audio format', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <?php
                    $audio_format_current_label = sprintf(
                        /* translators: 1: input audio format, 2: output audio format */
                        __('In: %1$s / Out: %2$s', 'gpt3-ai-content-generator'),
                        $input_audio_format ?: 'pcm16',
                        $output_audio_format ?: 'pcm16'
                    );
                    ?>
                    <select
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_audio_format_combo_sheet"
                        class="aipkit_popover_option_select aipkit_popover_option_select--compact aipkit_audio_format_combo_select"
                        <?php echo $rt_controls_disabled ? 'disabled' : ''; ?>
                    >
                        <option value="current" selected><?php echo esc_html($audio_format_current_label); ?></option>
                        <optgroup label="<?php esc_attr_e('Input', 'gpt3-ai-content-generator'); ?>">
                            <option value="in:pcm16">pcm16</option>
                            <option value="in:g711_ulaw">g711_ulaw</option>
                            <option value="in:g711_alaw">g711_alaw</option>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e('Output', 'gpt3-ai-content-generator'); ?>">
                            <option value="out:pcm16">pcm16</option>
                            <option value="out:g711_ulaw">g711_ulaw</option>
                            <option value="out:g711_alaw">g711_alaw</option>
                        </optgroup>
                    </select>
                    <select
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_input_audio_format_sheet"
                        name="input_audio_format"
                        class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                        hidden
                        <?php echo $rt_controls_disabled ? 'disabled' : ''; ?>
                    >
                        <option value="pcm16" <?php selected($input_audio_format, 'pcm16'); ?>>pcm16</option>
                        <option value="g711_ulaw" <?php selected($input_audio_format, 'g711_ulaw'); ?>>g711_ulaw</option>
                        <option value="g711_alaw" <?php selected($input_audio_format, 'g711_alaw'); ?>>g711_alaw</option>
                    </select>
                    <select
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_output_audio_format_sheet"
                        name="output_audio_format"
                        class="aipkit_popover_option_select aipkit_popover_option_select--compact"
                        hidden
                        <?php echo $rt_controls_disabled ? 'disabled' : ''; ?>
                    >
                        <option value="pcm16" <?php selected($output_audio_format, 'pcm16'); ?>>pcm16</option>
                        <option value="g711_ulaw" <?php selected($output_audio_format, 'g711_ulaw'); ?>>g711_ulaw</option>
                        <option value="g711_alaw" <?php selected($output_audio_format, 'g711_alaw'); ?>>g711_alaw</option>
                    </select>
                </div>

                <div class="aipkit_audio_settings_field aipkit_audio_settings_field--wide aipkit_rt_dependent">
                    <label
                        class="aipkit_popover_option_label"
                        for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_speed_sheet"
                    >
                        <?php esc_html_e('Voice speed', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <div class="aipkit_popover_param_slider">
                        <input
                            type="range"
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_speed_sheet"
                            name="speed"
                            class="aipkit_form-input aipkit_range_slider aipkit_popover_slider"
                            min="0.25"
                            max="1.5"
                            step="0.05"
                            value="<?php echo esc_attr($speed); ?>"
                            <?php echo $rt_controls_disabled ? 'disabled' : ''; ?>
                        />
                        <span id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_speed_sheet_value" class="aipkit_popover_param_value"><?php echo esc_html(number_format($speed, 2)); ?></span>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
