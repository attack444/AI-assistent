<?php


namespace WPAICG\PostEnhancer\Ajax;

use WPAICG\Dashboard\Ajax\BaseDashboardAjaxHandler;
use WPAICG\Utils\AIPKit_Prompt_Sanitizer;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles AJAX requests for managing Post Enhancer custom actions.
 */
class AIPKit_Enhancer_Actions_Ajax_Handler extends BaseDashboardAjaxHandler
{
    private const OPTION_NAME = 'aipkit_enhancer_actions';
    public const MODULE_SLUG = 'settings';
    public const MAX_ACTIONS = 20;

    /**
     * Get the default set of actions.
     * @return array
     */
    public function get_default_actions_public(): array
    {
        return [
            [
                'id' => 'rewrite-' . wp_generate_uuid4(),
                'label' => __('Rewrite', 'gpt3-ai-content-generator'),
                'prompt' => 'Rewrite this to improve clarity and engagement: {selected_text}',
                'insert_position' => 'replace',
                'is_default' => true
            ],
            [
                'id' => 'expand-' . wp_generate_uuid4(),
                'label' => __('Expand', 'gpt3-ai-content-generator'),
                'prompt' => 'Expand on the following point: {selected_text}',
                'insert_position' => 'replace',
                'is_default' => true
            ],
            [
                'id' => 'fix_grammar-' . wp_generate_uuid4(),
                'label' => __('Fix grammar and spelling', 'gpt3-ai-content-generator'),
                'prompt' => 'Correct any spelling and grammar mistakes in the following text: {selected_text}',
                'insert_position' => 'replace',
                'is_default' => true
            ],
            [
                'id' => 'summarize-' . wp_generate_uuid4(),
                'label' => __('Summarize', 'gpt3-ai-content-generator'),
                'prompt' => 'Summarize the following text in 3–5 concise sentences while preserving key facts and tone: {selected_text}',
                'insert_position' => 'replace',
                'is_default' => true
            ],
            [
                'id' => 'outline-' . wp_generate_uuid4(),
                'label' => __('Create outline (H2/H3)', 'gpt3-ai-content-generator'),
                'prompt' => 'Create a clear outline from the following text using headings (## for H2, ### for H3) and short bullets as needed: {selected_text}',
                'insert_position' => 'replace',
                'is_default' => true
            ],
            [
                'id' => 'faqs-' . wp_generate_uuid4(),
                'label' => __('Generate FAQs', 'gpt3-ai-content-generator'),
                'prompt' => 'Generate 5–7 relevant FAQ questions and short answers based on this text. Use a simple Q/A format in Markdown. Text: {selected_text}',
                'insert_position' => 'replace',
                'is_default' => true
            ],
            [
                'id' => 'simplify-' . wp_generate_uuid4(),
                'label' => __('Simplify tone', 'gpt3-ai-content-generator'),
                'prompt' => 'Rewrite the following in a friendly, simple tone (grade 7–8 readability) while preserving meaning and structure: {selected_text}',
                'insert_position' => 'replace',
                'is_default' => true
            ],
        ];
    }

    /**
     * Present untouched legacy defaults with the current labels and named token
     * without mutating a user's saved option until they explicitly save or reset.
     *
     * @param array $actions Stored actions.
     * @return array
     */
    public function normalize_actions_for_ui_public(array $actions): array
    {
        $legacy_labels = [
            'Fix Grammar & Spelling' => __('Fix grammar and spelling', 'gpt3-ai-content-generator'),
            'Create Outline (H2/H3)' => __('Create outline (H2/H3)', 'gpt3-ai-content-generator'),
            'Simplify Tone' => __('Simplify tone', 'gpt3-ai-content-generator'),
        ];

        foreach ($actions as &$action) {
            if (!is_array($action) || empty($action['is_default'])) {
                continue;
            }
            if (isset($action['label'], $legacy_labels[$action['label']])) {
                $action['label'] = $legacy_labels[$action['label']];
            }
            if (isset($action['prompt']) && is_string($action['prompt'])) {
                $action['prompt'] = str_replace('%s', '{selected_text}', $action['prompt']);
            }
        }
        unset($action);

        return $actions;
    }

    /**
     * AJAX: Reset actions to defaults.
     */
    public function ajax_reset_actions(): void
    {
        $permission_check = $this->check_module_access_permissions(self::MODULE_SLUG, 'aipkit_enhancer_actions_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        $defaults = $this->get_default_actions_public();
        update_option(self::OPTION_NAME, $defaults, 'no');
        wp_send_json_success([
            'message' => __('Actions reset to defaults.', 'gpt3-ai-content-generator'),
            'actions' => $defaults,
        ]);
    }

    /**
     * AJAX: Reorder actions.
     */
    public function ajax_reorder_actions(): void
    {
        $permission_check = $this->check_module_access_permissions(self::MODULE_SLUG, 'aipkit_enhancer_actions_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled in the parent class.
        $post_data = wp_unslash($_POST);
        $order_raw = $post_data['order'] ?? [];
        if (!is_array($order_raw)) {
            $this->send_wp_error(new WP_Error('invalid_order', __('Invalid action order provided.', 'gpt3-ai-content-generator')));
            return;
        }

        $ordered_ids = array_values(
            array_filter(
                array_map('sanitize_text_field', $order_raw)
            )
        );

        $actions = get_option(self::OPTION_NAME, $this->get_default_actions_public());
        if (!is_array($actions) || empty($actions)) {
            wp_send_json_success([
                'actions' => [],
            ]);
        }

        $actions_by_id = [];
        foreach ($actions as $action) {
            $id = isset($action['id']) ? sanitize_text_field((string) $action['id']) : '';
            if ($id !== '') {
                $actions_by_id[$id] = $action;
            }
        }

        $reordered = [];
        foreach ($ordered_ids as $id) {
            if (isset($actions_by_id[$id])) {
                $reordered[] = $actions_by_id[$id];
                unset($actions_by_id[$id]);
            }
        }

        // Append any remaining actions in their original order.
        if (!empty($actions_by_id)) {
            foreach ($actions as $action) {
                $id = isset($action['id']) ? sanitize_text_field((string) $action['id']) : '';
                if ($id !== '' && isset($actions_by_id[$id])) {
                    $reordered[] = $action;
                    unset($actions_by_id[$id]);
                }
            }
        }

        update_option(self::OPTION_NAME, $reordered, 'no');
        wp_send_json_success([
            'message' => __('Action order updated.', 'gpt3-ai-content-generator'),
            'actions' => $this->normalize_actions_for_ui_public($reordered),
        ]);
    }

    /**
     * AJAX handler to get all custom and default actions.
     */
    public function ajax_get_actions(): void
    {
        $permission_check = $this->check_module_access_permissions(self::MODULE_SLUG, 'aipkit_enhancer_actions_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        $actions = get_option(self::OPTION_NAME);
        if ($actions === false || !is_array($actions)) {
            $actions = $this->get_default_actions_public();
        }

        wp_send_json_success([
            'actions' => $this->normalize_actions_for_ui_public($actions),
        ]);
    }

    /**
     * AJAX handler to save or update an action.
     */
    public function ajax_save_action(): void
    {
        $permission_check = $this->check_module_access_permissions(self::MODULE_SLUG, 'aipkit_enhancer_actions_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled in the parent class.
        $post_data = wp_unslash($_POST);
        $action_id = isset($post_data['id']) && !empty($post_data['id']) ? sanitize_text_field((string) $post_data['id']) : null;
        $label = isset($post_data['label']) ? sanitize_text_field((string) $post_data['label']) : '';
        $prompt = isset($post_data['prompt']) ? AIPKit_Prompt_Sanitizer::sanitize($post_data['prompt']) : '';
        $allowed_positions = ['replace', 'after', 'before'];
        $insert_position_raw = isset($post_data['insert_position']) ? sanitize_key((string) $post_data['insert_position']) : 'replace';
        $insert_position = in_array($insert_position_raw, $allowed_positions, true) ? $insert_position_raw : 'replace';

        if (empty($label) || empty($prompt)) {
            $this->send_wp_error(new WP_Error('missing_data', __('Label and prompt are required.', 'gpt3-ai-content-generator')));
            return;
        }

        $actions = get_option(self::OPTION_NAME, $this->get_default_actions_public());
        if (!is_array($actions)) {
            $actions = $this->get_default_actions_public(); // Fallback if option is corrupted
        }

        $found = false;
        if ($action_id && strpos($action_id, 'new-') !== 0) { // It's an existing action
            foreach ($actions as &$action) {
                if (isset($action['id']) && $action['id'] === $action_id) {
                    // A default action that is edited becomes a custom action.
                    if (isset($action['is_default'])) {
                        $action['is_default'] = false;
                    }
                    $action['label'] = $label;
                    $action['prompt'] = $prompt;
                    $action['insert_position'] = $insert_position;
                    $found = true;
                    break;
                }
            }
            unset($action);
        }

        $saved_action = null;
        if (!$found) {
            // It's a new action
            if (count($actions) >= self::MAX_ACTIONS) {
                $this->send_wp_error(new WP_Error('limit_reached', __('You have reached the maximum of 20 actions.', 'gpt3-ai-content-generator')));
                return;
            }
            $new_action = [
                'id' => 'custom-' . wp_generate_uuid4(),
                'label' => $label,
                'prompt' => $prompt,
                'insert_position' => $insert_position,
                'is_default' => false
            ];
            $actions[] = $new_action;
            $saved_action = $new_action;
        } else {
            $saved_action_array = array_filter($actions, function ($a) use ($action_id) {
                return isset($a['id']) && $a['id'] === $action_id;
            });
            $saved_action = reset($saved_action_array);
        }

        update_option(self::OPTION_NAME, $actions, 'no');
        wp_send_json_success([
            'message' => __('Action saved successfully.', 'gpt3-ai-content-generator'),
            'action' => $saved_action,
        ]);
    }

    /**
     * AJAX handler to delete an action.
     */
    public function ajax_delete_action(): void
    {
        $permission_check = $this->check_module_access_permissions(self::MODULE_SLUG, 'aipkit_enhancer_actions_nonce');
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reason: Nonce verification is handled in the parent class.
        $action_id_to_delete = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : null;

        if (empty($action_id_to_delete)) {
            $this->send_wp_error(new WP_Error('missing_id', __('Action ID is required.', 'gpt3-ai-content-generator')));
            return;
        }

        $actions = get_option(self::OPTION_NAME, []);
        if (!is_array($actions)) {
            wp_send_json_success([
                'message' => __('No actions to delete.', 'gpt3-ai-content-generator'),
                'actions' => [],
            ]);
            return;
        }

        $updated_actions = array_filter($actions, function ($action) use ($action_id_to_delete) {
            return !isset($action['id']) || $action['id'] !== $action_id_to_delete;
        });

        update_option(self::OPTION_NAME, array_values($updated_actions), 'no');
        wp_send_json_success([
            'message' => __('Action deleted successfully.', 'gpt3-ai-content-generator'),
            'actions' => $this->normalize_actions_for_ui_public(array_values($updated_actions)),
        ]);
    }
}
