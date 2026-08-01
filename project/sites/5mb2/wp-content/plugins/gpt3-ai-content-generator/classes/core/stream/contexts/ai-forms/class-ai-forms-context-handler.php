<?php


namespace WPAICG\Core\Stream\Contexts\AIForms;

use WPAICG\Chat\Storage\LogStorage;
use WPAICG\AIForms\Storage\AIPKit_AI_Form_Storage;
use WPAICG\Core\TokenManager\AIPKit_Token_Manager;
use WP_Error;
// Ensure dependencies for logic file are loaded if not by main loader
use WPAICG\AIPKit_Providers;
use WPAICG\AIPKIT_AI_Settings;
use WPAICG\Core\Providers\Google\GoogleSettingsHandler;
use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\Vector\AIPKit_Vector_Store_Manager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load method logic file
require_once __DIR__ . '/fn-process-ai-forms.php';

/**
 * Handles processing stream requests specifically for the 'ai_forms' context.
 */
class SSEAIFormsStreamContextHandler
{
    private $log_storage;
    private $ai_form_storage;
    private $token_manager;
    private $ai_caller;
    private $vector_store_manager;

    public function __construct(
        LogStorage $log_storage,
        AIPKit_AI_Form_Storage $ai_form_storage,
        AIPKit_Token_Manager $token_manager,
        ?AIPKit_AI_Caller $ai_caller,
        ?AIPKit_Vector_Store_Manager $vector_store_manager
    ) {
        $this->log_storage = $log_storage;
        $this->ai_form_storage = $ai_form_storage;
        $this->token_manager = $token_manager;
        $this->ai_caller = $ai_caller;
        $this->vector_store_manager = $vector_store_manager;

        // Dependencies should be loaded by AIPKit_Dependency_Loader.
        // No late require_once calls here.
    }

    // Getter for LogStorage, used by the logic function
    public function get_log_storage(): LogStorage
    {
        return $this->log_storage;
    }
    public function get_ai_form_storage(): AIPKit_AI_Form_Storage
    {
        return $this->ai_form_storage;
    }
    public function get_token_manager(): AIPKit_Token_Manager
    {
        return $this->token_manager;
    }
    public function get_ai_caller(): ?AIPKit_AI_Caller
    {
        return $this->ai_caller;
    }
    public function get_vector_store_manager(): ?AIPKit_Vector_Store_Manager
    {
        return $this->vector_store_manager;
    }


    /**
     * Processes an AI Forms stream request.
     * @param array $cached_data Contains 'stream_context', 'form_id', 'user_input_values'.
     * @param array $get_params  Original $_GET parameters.
     * @return array|WP_Error Prepared data for SSEStreamProcessor or WP_Error.
     */
    public function process(array $cached_data, array $get_params)
    {
        return process_ai_forms_logic($this, $cached_data, $get_params);
    }
}
