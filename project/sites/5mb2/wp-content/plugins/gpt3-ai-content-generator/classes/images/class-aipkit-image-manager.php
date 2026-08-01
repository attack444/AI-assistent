<?php


namespace WPAICG\Images;

use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file only uses local helper/template variables and does not define public globals.

// Load method logic files
$manager_path = __DIR__ . '/manager/';
require_once $manager_path . '__construct.php';
require_once $manager_path . 'init_hooks.php';
require_once $manager_path . 'get_image_settings.php';
require_once $manager_path . 'generate_image.php';
require_once $manager_path . 'emit-generated-event.php';
require_once $manager_path . 'ajax/methods.php';
require_once $manager_path . 'log/log_image_generation_attempt.php';
require_once $manager_path . 'utils/parse_edit_source_image_upload.php';
require_once $manager_path . 'utils/send_wp_error.php';


/**
 * AIPKit_Image_Manager (Facade)
 * Main class for handling image generation functionality.
 * Delegates logic to namespaced functions.
 */
class AIPKit_Image_Manager
{
    public const MODULE_SLUG = 'image_generator';
    public const TOKENS_PER_IMAGE = 2000;

    private $log_storage;
    private $settings_ajax_handler;
    private $image_settings_cache = null;
    private $token_manager;

    public function __construct()
    {
        Manager\constructor_logic($this);
    }

    public function init_hooks()
    {
        Manager\init_hooks_logic($this);
    }

    public function get_image_settings(): array
    {
        return Manager\get_image_settings_logic($this);
    }

    /**
     * @return mixed[]|\WP_Error
     */
    public function generate_image(string $prompt, array $options = [], ?int $wp_user_id = null)
    {
        return Manager\generate_image_logic($this, $prompt, $options, $wp_user_id);
    }

    public function emit_generated_event(
        string $prompt,
        array $result,
        array $options = [],
        ?int $user_id = null,
        ?string $session_id = null
    ): void {
        Manager\emit_generated_event_logic($this, $prompt, $result, $options, $user_id, $session_id);
    }

    public function ajax_generate_image()
    {
        Manager\Ajax\ajax_generate_image_logic($this);
    }

    public function ajax_delete_generated_image()
    {
        Manager\Ajax\ajax_delete_generated_image_logic();
    }

    public function ajax_load_more_image_history()
    {
        Manager\Ajax\ajax_load_more_image_history_logic();
    }

    public function ajax_check_video_status()
    {
        Manager\Ajax\ajax_check_video_status_logic($this);
    }

    /**
     * @param mixed[]|\WP_Error $result
     */
    public function log_image_generation_attempt(
        string $conversation_uuid,
        string $extracted_prompt,
        array $request_options_for_log,
        $result,
        ?array $usage_data,
        ?int $user_id,
        ?string $session_id,
        ?string $client_ip,
        ?int $bot_id_for_log = null,
        ?string $user_wp_role = null,
        ?string $bot_response_message_id = null
    ) {
        Manager\Log\log_image_generation_attempt_logic($this, $conversation_uuid, $extracted_prompt, $request_options_for_log, $result, $usage_data, $user_id, $session_id, $client_ip, $bot_id_for_log, $user_wp_role, $bot_response_message_id);
    }

    public function send_wp_error(WP_Error $error)
    {
        Manager\Utils\send_wp_error_logic($error);
    }

    // --- Getters and Setters for externalized logic functions ---
    public function get_log_storage()
    {
        return $this->log_storage;
    }
    public function set_log_storage($storage)
    {
        $this->log_storage = $storage;
    }
    public function get_settings_ajax_handler()
    {
        return $this->settings_ajax_handler;
    }
    public function set_settings_ajax_handler($handler)
    {
        $this->settings_ajax_handler = $handler;
    }
    public function get_image_settings_cache()
    {
        return $this->image_settings_cache;
    }
    public function set_image_settings_cache($cache)
    {
        $this->image_settings_cache = $cache;
    }
    public function get_token_manager()
    {
        return $this->token_manager;
    }
    public function set_token_manager($manager)
    {
        $this->token_manager = $manager;
    }
    // --- End Getters and Setters ---
}
