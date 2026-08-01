<?php

namespace WPAICG\ContentWriter\Ajax\Actions;

use WPAICG\ContentWriter\Ajax\AIPKit_Content_Writer_Base_Ajax_Action;
use WPAICG\ContentWriter\Ajax\Actions\CreateTask;
use WPAICG\aipkit_dashboard;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

$logic_path = __DIR__ . '/create-task/';
require_once $logic_path . 'methods.php';

$modules_path = WPAICG_PLUGIN_DIR . 'classes/autogpt/cron/event-processor/trigger/module/';
require_once $modules_path . 'methods.php';

/**
 * Prepares a batch of content items for inline generation (non-single modes).
 */
class AIPKit_Content_Writer_Prepare_Batch_Action extends AIPKit_Content_Writer_Base_Ajax_Action
{
    public function handle()
    {
        $permission_check = $this->check_module_access_permissions('content-writer', 'aipkit_content_writer_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in check_module_access_permissions.
        $raw_settings = isset($_POST) ? wp_unslash($_POST) : [];
        $generation_mode = isset($raw_settings['cw_generation_mode'])
            ? sanitize_key($raw_settings['cw_generation_mode'])
            : 'task';
        if ($generation_mode === 'single') {
            $generation_mode = 'task';
        }

        $allowed_modes = ['task', 'csv', 'rss', 'url', 'gsheets'];
        if (!in_array($generation_mode, $allowed_modes, true)) {
            $this->send_wp_error(new WP_Error('invalid_generation_mode', __('Invalid generation mode.', 'gpt3-ai-content-generator'), ['status' => 400]));
            return;
        }

        if ($generation_mode === 'csv') {
            $raw_settings['content_title'] = $raw_settings['content_title_csv'] ?? '';
        }

        if ($generation_mode !== 'task') {
            unset($raw_settings['content_title_bulk']);
            if ($generation_mode !== 'csv') {
                $raw_settings['content_title'] = '';
            }
        }

        if (in_array($generation_mode, ['rss', 'url', 'gsheets'], true) && (!class_exists(aipkit_dashboard::class) || !aipkit_dashboard::is_pro_plan())) {
            $this->send_wp_error(new WP_Error('pro_feature_required', __('This generation mode is a Pro feature.', 'gpt3-ai-content-generator'), ['status' => 403]));
            return;
        }

        $task_frequency = isset($raw_settings['task_frequency']) ? sanitize_key($raw_settings['task_frequency']) : 'manual';
        $task_status = isset($raw_settings['task_status']) ? sanitize_key($raw_settings['task_status']) : 'active';

        $task_config = CreateTask\build_content_writer_config_logic($raw_settings, $task_frequency, $task_status);
        if (is_wp_error($task_config)) {
            $this->send_wp_error($task_config);
            return;
        }

        $validation = CreateTask\validate_task_requirements_logic($task_config);
        if (is_wp_error($validation)) {
            $this->send_wp_error($validation);
            return;
        }

        $items = [];
        $scraped_contexts = [];

        switch ($generation_mode) {
            case 'task':
            case 'csv':
                $items = \WPAICG\AutoGPT\Cron\EventProcessor\Trigger\Modules\manual_mode_generate_items_logic($task_config);
                break;
            case 'rss':
                $items = \WPAICG\AutoGPT\Cron\EventProcessor\Trigger\Modules\rss_mode_generate_items_logic(0, $task_config, null);
                break;
            case 'url':
                $url_result = \WPAICG\AutoGPT\Cron\EventProcessor\Trigger\Modules\url_mode_generate_items_logic(0, $task_config);
                if (!is_wp_error($url_result)) {
                    $items = $url_result['topics'] ?? [];
                    $scraped_contexts = $url_result['contexts'] ?? [];
                } else {
                    $items = $url_result;
                }
                break;
            case 'gsheets':
                $items = \WPAICG\AutoGPT\Cron\EventProcessor\Trigger\Modules\gsheets_mode_generate_items_logic(0, $task_config);
                break;
        }

        if (is_wp_error($items)) {
            $this->send_wp_error($items);
            return;
        }

        if (!is_array($items)) {
            $items = [];
        }

        $prepared = [];
        $item_index = 0;
        $limit = 100;

        foreach ($items as $item_data) {
            if (count($prepared) >= $limit) {
                break;
            }
            $item_config = \WPAICG\AutoGPT\Cron\EventProcessor\Trigger\Modules\prepare_item_config_logic(
                $item_data,
                $task_config,
                $scraped_contexts
            );

            $topic = $item_config['content_title'] ?? '';
            if ($topic === '') {
                $item_index++;
                continue;
            }
            $topic_label = sanitize_text_field($topic);

            $schedule_gmt = \WPAICG\AutoGPT\Cron\EventProcessor\Trigger\Modules\compute_item_schedule_gmt_logic(
                $item_data,
                $task_config,
                $item_index,
                $generation_mode
            );

            if ($schedule_gmt) {
                $local_datetime = get_date_from_gmt($schedule_gmt, 'Y-m-d H:i:s');
                $parts = explode(' ', $local_datetime);
                if (count($parts) === 2) {
                    $item_config['post_schedule_date'] = $parts[0];
                    $item_config['post_schedule_time'] = substr($parts[1], 0, 5);
                }
            }

            $prepared[] = [
                'topic' => $topic_label,
                'item_config' => $item_config,
            ];

            $item_index++;
        }

        wp_send_json_success([
            'items' => $prepared,
            'total' => count($items),
            'returned' => count($prepared),
            'limit' => $limit,
        ]);
    }
}
