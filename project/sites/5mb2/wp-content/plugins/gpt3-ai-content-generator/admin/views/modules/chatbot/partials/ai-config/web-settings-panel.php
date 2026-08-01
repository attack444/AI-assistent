<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.
$supports_web_toggle_default = in_array($current_provider_for_this_bot, ['OpenAI', 'Google', 'Claude', 'OpenRouter', 'xAI'], true);
?>

<div class="aipkit_popover_options_list aipkit_popover_options_list--web aipkit_web_settings_panel">
    <div class="aipkit_web_settings_group aipkit_web_settings_group--common">
        <div class="aipkit_web_settings_grid aipkit_web_settings_grid--two">
            <div class="aipkit_popover_option_row aipkit_web_settings_field aipkit_web_toggle_default_row" style="<?php echo $supports_web_toggle_default ? '' : 'display:none;'; ?>">
                <div class="aipkit_popover_option_main">
                    <label
                        class="aipkit_popover_option_label"
                        for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_web_toggle_default_on_modal"
                    >
                        <?php esc_html_e('Search by default', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <select
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_web_toggle_default_on_modal"
                        name="web_toggle_default_on"
                        class="aipkit_popover_option_select aipkit_popover_option_select--compact aipkit_web_toggle_default_on"
                    >
                        <option value="1" <?php selected($web_toggle_default_on_val, '1'); ?>><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                        <option value="0" <?php selected($web_toggle_default_on_val, '0'); ?>><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                    </select>
                </div>
            </div>

            <div class="aipkit_popover_option_row aipkit_web_settings_field aipkit_show_sources_row">
                <div class="aipkit_popover_option_main">
                    <label
                        class="aipkit_popover_option_label"
                        for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_show_sources_modal"
                    >
                        <?php esc_html_e('Show sources', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <select
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_show_sources_modal"
                        name="show_sources"
                        class="aipkit_popover_option_select aipkit_popover_option_select--compact aipkit_show_sources_toggle"
                    >
                        <option value="1" <?php selected($show_sources_val, '1'); ?>><?php esc_html_e('Yes', 'gpt3-ai-content-generator'); ?></option>
                        <option value="0" <?php selected($show_sources_val, '0'); ?>><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                    </select>
                </div>
            </div>

            <div class="aipkit_popover_option_row aipkit_web_settings_field aipkit_sources_label_row">
                <div class="aipkit_popover_option_main">
                    <label
                        class="aipkit_popover_option_label"
                        for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_sources_label_modal"
                    >
                        <?php esc_html_e('Sources title', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <input
                        type="text"
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_sources_label_modal"
                        name="sources_label"
                        class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                        value="<?php echo esc_attr($sources_label_val); ?>"
                        placeholder="<?php esc_attr_e('Sources', 'gpt3-ai-content-generator'); ?>"
                    />
                </div>
            </div>

            <div class="aipkit_popover_option_row aipkit_web_settings_field aipkit_searching_web_text_row">
                <div class="aipkit_popover_option_main">
                    <label
                        class="aipkit_popover_option_label"
                        for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_searching_web_text_modal"
                    >
                        <?php esc_html_e('Searching message', 'gpt3-ai-content-generator'); ?>
                    </label>
                    <input
                        type="text"
                        id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_searching_web_text_modal"
                        name="searching_web_text"
                        class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                        value="<?php echo esc_attr($searching_web_text_val); ?>"
                        placeholder="<?php esc_attr_e('Searching web...', 'gpt3-ai-content-generator'); ?>"
                    />
                </div>
            </div>
        </div>
    </div>

    <div class="aipkit_popover_option_group aipkit_web_modal_section_openai" style="<?php echo ($current_provider_for_this_bot === 'OpenAI') ? '' : 'display:none;'; ?>">
        <div class="aipkit_openai_web_search_conditional_settings aipkit_web_provider_settings" style="<?php echo ($current_provider_for_this_bot === 'OpenAI' && $openai_web_search_enabled_val === '1') ? '' : 'display:none;'; ?>">
            <div class="aipkit_web_settings_grid aipkit_web_settings_grid--two">
                <div class="aipkit_popover_option_row aipkit_web_settings_field">
                    <div class="aipkit_popover_option_main">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_web_search_context_size_modal"
                        >
                            <?php esc_html_e('Search depth', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <select
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_web_search_context_size_modal"
                            name="openai_web_search_context_size"
                            class="aipkit_popover_option_select"
                        >
                            <option value="low" <?php selected($openai_web_search_context_size_val, 'low'); ?>><?php esc_html_e('Light', 'gpt3-ai-content-generator'); ?></option>
                            <option value="medium" <?php selected($openai_web_search_context_size_val, 'medium'); ?>><?php esc_html_e('Balanced', 'gpt3-ai-content-generator'); ?></option>
                            <option value="high" <?php selected($openai_web_search_context_size_val, 'high'); ?>><?php esc_html_e('Deep', 'gpt3-ai-content-generator'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="aipkit_popover_option_row aipkit_web_settings_field">
                    <div class="aipkit_popover_option_main">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_web_search_loc_type_modal"
                        >
                            <?php esc_html_e('Location', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <select
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_web_search_loc_type_modal"
                            name="openai_web_search_loc_type"
                            class="aipkit_popover_option_select aipkit_openai_web_search_loc_type_select"
                        >
                            <option value="none" <?php selected($openai_web_search_loc_type_val, 'none'); ?>><?php esc_html_e('Off', 'gpt3-ai-content-generator'); ?></option>
                            <option value="approximate" <?php selected($openai_web_search_loc_type_val, 'approximate'); ?>><?php esc_html_e('Approximate', 'gpt3-ai-content-generator'); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="aipkit_openai_web_search_location_details aipkit_web_location_details" style="<?php echo ($current_provider_for_this_bot === 'OpenAI' && $openai_web_search_enabled_val === '1' && $openai_web_search_loc_type_val === 'approximate') ? '' : 'display:none;'; ?>">
                <div class="aipkit_web_settings_grid aipkit_web_settings_grid--two">
                    <div class="aipkit_popover_option_row aipkit_web_settings_field">
                        <div class="aipkit_popover_option_main">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_web_search_loc_country_modal"
                            >
                                <?php esc_html_e('Country', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <input
                                type="text"
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_web_search_loc_country_modal"
                                name="openai_web_search_loc_country"
                                class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                                value="<?php echo esc_attr($openai_web_search_loc_country_val); ?>"
                                placeholder="<?php esc_attr_e('US', 'gpt3-ai-content-generator'); ?>"
                                maxlength="2"
                            />
                        </div>
                    </div>

                    <div class="aipkit_popover_option_row aipkit_web_settings_field">
                        <div class="aipkit_popover_option_main">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_web_search_loc_city_modal"
                            >
                                <?php esc_html_e('City', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <input
                                type="text"
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_web_search_loc_city_modal"
                                name="openai_web_search_loc_city"
                                class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                                value="<?php echo esc_attr($openai_web_search_loc_city_val); ?>"
                                placeholder="<?php esc_attr_e('London', 'gpt3-ai-content-generator'); ?>"
                            />
                        </div>
                    </div>

                    <div class="aipkit_popover_option_row aipkit_web_settings_field">
                        <div class="aipkit_popover_option_main">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_web_search_loc_region_modal"
                            >
                                <?php esc_html_e('Region', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <input
                                type="text"
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_web_search_loc_region_modal"
                                name="openai_web_search_loc_region"
                                class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                                value="<?php echo esc_attr($openai_web_search_loc_region_val); ?>"
                                placeholder="<?php esc_attr_e('California', 'gpt3-ai-content-generator'); ?>"
                            />
                        </div>
                    </div>

                    <div class="aipkit_popover_option_row aipkit_web_settings_field">
                        <div class="aipkit_popover_option_main">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_web_search_loc_timezone_modal"
                            >
                                <?php esc_html_e('Timezone', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <input
                                type="text"
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openai_web_search_loc_timezone_modal"
                                name="openai_web_search_loc_timezone"
                                class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                                value="<?php echo esc_attr($openai_web_search_loc_timezone_val); ?>"
                                placeholder="<?php esc_attr_e('America/Chicago', 'gpt3-ai-content-generator'); ?>"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="aipkit_popover_option_group aipkit_web_modal_section_claude" style="<?php echo ($current_provider_for_this_bot === 'Claude') ? '' : 'display:none;'; ?>">
        <div class="aipkit_claude_web_search_conditional_settings aipkit_web_provider_settings" style="<?php echo ($current_provider_for_this_bot === 'Claude' && $claude_web_search_enabled_val === '1') ? '' : 'display:none;'; ?>">
            <div class="aipkit_web_settings_grid aipkit_web_settings_grid--two">
                <div class="aipkit_popover_option_row aipkit_web_settings_field">
                    <div class="aipkit_popover_option_main">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_max_uses_modal"
                        >
                            <?php esc_html_e('Search limit', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="number"
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_max_uses_modal"
                            name="claude_web_search_max_uses"
                            class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                            min="1"
                            max="20"
                            step="1"
                            value="<?php echo esc_attr($claude_web_search_max_uses_val); ?>"
                        />
                    </div>
                </div>

                <div class="aipkit_popover_option_row aipkit_web_settings_field">
                    <div class="aipkit_popover_option_main">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_loc_type_modal"
                        >
                            <?php esc_html_e('Location', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <select
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_loc_type_modal"
                            name="claude_web_search_loc_type"
                            class="aipkit_popover_option_select aipkit_claude_web_search_loc_type_select"
                        >
                            <option value="none" <?php selected($claude_web_search_loc_type_val, 'none'); ?>><?php esc_html_e('Off', 'gpt3-ai-content-generator'); ?></option>
                            <option value="approximate" <?php selected($claude_web_search_loc_type_val, 'approximate'); ?>><?php esc_html_e('Approximate', 'gpt3-ai-content-generator'); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="aipkit_claude_web_search_location_details aipkit_web_location_details" style="<?php echo ($current_provider_for_this_bot === 'Claude' && $claude_web_search_enabled_val === '1' && $claude_web_search_loc_type_val === 'approximate') ? '' : 'display:none;'; ?>">
                <div class="aipkit_web_settings_grid aipkit_web_settings_grid--two">
                    <div class="aipkit_popover_option_row aipkit_web_settings_field">
                        <div class="aipkit_popover_option_main">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_loc_country_modal"
                            >
                                <?php esc_html_e('Country', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <input
                                type="text"
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_loc_country_modal"
                                name="claude_web_search_loc_country"
                                class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                                value="<?php echo esc_attr($claude_web_search_loc_country_val); ?>"
                                placeholder="<?php esc_attr_e('US', 'gpt3-ai-content-generator'); ?>"
                                maxlength="2"
                            />
                        </div>
                    </div>

                    <div class="aipkit_popover_option_row aipkit_web_settings_field">
                        <div class="aipkit_popover_option_main">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_loc_city_modal"
                            >
                                <?php esc_html_e('City', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <input
                                type="text"
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_loc_city_modal"
                                name="claude_web_search_loc_city"
                                class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                                value="<?php echo esc_attr($claude_web_search_loc_city_val); ?>"
                                placeholder="<?php esc_attr_e('London', 'gpt3-ai-content-generator'); ?>"
                            />
                        </div>
                    </div>

                    <div class="aipkit_popover_option_row aipkit_web_settings_field">
                        <div class="aipkit_popover_option_main">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_loc_region_modal"
                            >
                                <?php esc_html_e('Region', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <input
                                type="text"
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_loc_region_modal"
                                name="claude_web_search_loc_region"
                                class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                                value="<?php echo esc_attr($claude_web_search_loc_region_val); ?>"
                                placeholder="<?php esc_attr_e('California', 'gpt3-ai-content-generator'); ?>"
                            />
                        </div>
                    </div>

                    <div class="aipkit_popover_option_row aipkit_web_settings_field">
                        <div class="aipkit_popover_option_main">
                            <label
                                class="aipkit_popover_option_label"
                                for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_loc_timezone_modal"
                            >
                                <?php esc_html_e('Timezone', 'gpt3-ai-content-generator'); ?>
                            </label>
                            <input
                                type="text"
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_loc_timezone_modal"
                                name="claude_web_search_loc_timezone"
                                class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                                value="<?php echo esc_attr($claude_web_search_loc_timezone_val); ?>"
                                placeholder="<?php esc_attr_e('America/Chicago', 'gpt3-ai-content-generator'); ?>"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="aipkit_web_settings_grid aipkit_web_settings_grid--two">
                <div class="aipkit_popover_option_row aipkit_web_settings_field">
                    <div class="aipkit_popover_option_main">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_allowed_domains_modal"
                        >
                            <?php esc_html_e('Allowed sites', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="text"
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_allowed_domains_modal"
                            name="claude_web_search_allowed_domains"
                            class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                            value="<?php echo esc_attr($claude_web_search_allowed_domains_val); ?>"
                            placeholder="<?php esc_attr_e('example.com, docs.example.com', 'gpt3-ai-content-generator'); ?>"
                        />
                    </div>
                </div>

                <div class="aipkit_popover_option_row aipkit_web_settings_field">
                    <div class="aipkit_popover_option_main">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_blocked_domains_modal"
                        >
                            <?php esc_html_e('Blocked sites', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="text"
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_blocked_domains_modal"
                            name="claude_web_search_blocked_domains"
                            class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                            value="<?php echo esc_attr($claude_web_search_blocked_domains_val); ?>"
                            placeholder="<?php esc_attr_e('example.com, ads.example.org', 'gpt3-ai-content-generator'); ?>"
                        />
                    </div>
                </div>

                <div class="aipkit_popover_option_row aipkit_web_settings_field">
                    <div class="aipkit_popover_option_main">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_cache_ttl_modal"
                        >
                            <?php esc_html_e('Cache results', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <select
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_claude_web_search_cache_ttl_modal"
                            name="claude_web_search_cache_ttl"
                            class="aipkit_popover_option_select"
                        >
                            <option value="none" <?php selected($claude_web_search_cache_ttl_val, 'none'); ?>><?php esc_html_e('No', 'gpt3-ai-content-generator'); ?></option>
                            <option value="5m" <?php selected($claude_web_search_cache_ttl_val, '5m'); ?>><?php esc_html_e('5 minutes', 'gpt3-ai-content-generator'); ?></option>
                            <option value="1h" <?php selected($claude_web_search_cache_ttl_val, '1h'); ?>><?php esc_html_e('1 hour', 'gpt3-ai-content-generator'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="aipkit_popover_option_group aipkit_web_modal_section_openrouter" style="<?php echo ($current_provider_for_this_bot === 'OpenRouter') ? '' : 'display:none;'; ?>">
        <div class="aipkit_openrouter_web_search_conditional_settings aipkit_web_provider_settings" style="<?php echo ($current_provider_for_this_bot === 'OpenRouter' && $openrouter_web_search_enabled_val === '1') ? '' : 'display:none;'; ?>">
            <p class="aipkit_web_settings_note">
                <?php esc_html_e('Availability depends on the selected model.', 'gpt3-ai-content-generator'); ?>
            </p>
            <div class="aipkit_web_settings_grid aipkit_web_settings_grid--two">
                <div class="aipkit_popover_option_row aipkit_web_settings_field">
                    <div class="aipkit_popover_option_main">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openrouter_web_search_engine_modal"
                        >
                            <?php esc_html_e('Search provider', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <select
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openrouter_web_search_engine_modal"
                            name="openrouter_web_search_engine"
                            class="aipkit_popover_option_select"
                        >
                            <option value="auto" <?php selected($openrouter_web_search_engine_val, 'auto'); ?>><?php esc_html_e('Auto', 'gpt3-ai-content-generator'); ?></option>
                            <option value="native" <?php selected($openrouter_web_search_engine_val, 'native'); ?>><?php esc_html_e('Native', 'gpt3-ai-content-generator'); ?></option>
                            <option value="exa" <?php selected($openrouter_web_search_engine_val, 'exa'); ?>><?php esc_html_e('Exa', 'gpt3-ai-content-generator'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="aipkit_popover_option_row aipkit_web_settings_field">
                    <div class="aipkit_popover_option_main">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openrouter_web_search_max_results_modal"
                        >
                            <?php esc_html_e('Results', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="number"
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openrouter_web_search_max_results_modal"
                            name="openrouter_web_search_max_results"
                            class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                            min="1"
                            max="10"
                            step="1"
                            value="<?php echo esc_attr($openrouter_web_search_max_results_val); ?>"
                        />
                    </div>
                </div>

                <div class="aipkit_popover_option_row aipkit_web_settings_field aipkit_web_settings_field--wide">
                    <div class="aipkit_popover_option_main">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openrouter_web_search_search_prompt_modal"
                        >
                            <?php esc_html_e('Search instructions', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <input
                            type="text"
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_openrouter_web_search_search_prompt_modal"
                            name="openrouter_web_search_search_prompt"
                            class="aipkit_popover_option_input aipkit_popover_option_input--framed"
                            value="<?php echo esc_attr($openrouter_web_search_search_prompt_val); ?>"
                            placeholder="<?php esc_attr_e('Optional guidance for searches', 'gpt3-ai-content-generator'); ?>"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="aipkit_popover_option_group aipkit_web_modal_section_google" style="<?php echo ($current_provider_for_this_bot === 'Google') ? '' : 'display:none;'; ?>">
        <div class="aipkit_google_search_grounding_conditional_settings aipkit_web_provider_settings" style="<?php echo ($current_provider_for_this_bot === 'Google' && $google_search_grounding_enabled_val === '1') ? '' : 'display:none;'; ?>">
            <div class="aipkit_web_settings_grid aipkit_web_settings_grid--two">
                <div class="aipkit_popover_option_row aipkit_web_settings_field">
                    <div class="aipkit_popover_option_main">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_google_grounding_mode_modal"
                        >
                            <?php esc_html_e('Search mode', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <select
                            id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_google_grounding_mode_modal"
                            name="google_grounding_mode"
                            class="aipkit_popover_option_select aipkit_google_grounding_mode_select"
                        >
                            <option value="DEFAULT_MODE" <?php selected($google_grounding_mode_val, 'DEFAULT_MODE'); ?>><?php esc_html_e('Automatic', 'gpt3-ai-content-generator'); ?></option>
                            <option value="MODE_DYNAMIC" <?php selected($google_grounding_mode_val, 'MODE_DYNAMIC'); ?>><?php esc_html_e('Dynamic', 'gpt3-ai-content-generator'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="aipkit_popover_option_row aipkit_web_settings_field aipkit_google_grounding_dynamic_threshold_container" style="<?php echo ($current_provider_for_this_bot === 'Google' && $google_search_grounding_enabled_val === '1' && $google_grounding_mode_val === 'MODE_DYNAMIC') ? '' : 'display:none;'; ?>">
                    <div class="aipkit_popover_option_main">
                        <label
                            class="aipkit_popover_option_label"
                            for="aipkit_bot_<?php echo esc_attr($bot_id); ?>_google_grounding_dynamic_threshold_modal"
                        >
                            <?php esc_html_e('Search threshold', 'gpt3-ai-content-generator'); ?>
                        </label>
                        <div class="aipkit_popover_param_slider">
                            <input
                                type="range"
                                id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_google_grounding_dynamic_threshold_modal"
                                name="google_grounding_dynamic_threshold"
                                class="aipkit_form-input aipkit_range_slider aipkit_popover_slider"
                                min="0.0"
                                max="1.0"
                                step="0.01"
                                value="<?php echo esc_attr($google_grounding_dynamic_threshold_val); ?>"
                            />
                            <span id="aipkit_bot_<?php echo esc_attr($bot_id); ?>_google_grounding_dynamic_threshold_modal_value" class="aipkit_popover_param_value"><?php echo esc_html(number_format($google_grounding_dynamic_threshold_val, 2)); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
