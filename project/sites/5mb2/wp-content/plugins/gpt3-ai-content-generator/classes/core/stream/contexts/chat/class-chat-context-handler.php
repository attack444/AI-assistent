<?php


namespace WPAICG\Core\Stream\Contexts\Chat;

use WPAICG\Chat\Storage\BotStorage;
use WPAICG\Chat\Storage\LogStorage;
use WPAICG\Core\TokenManager\AIPKit_Token_Manager;
use WPAICG\Core\Stream\Vector\SSEVectorContextHelper;
use WPAICG\Chat\Core\AIService as ChatAIService;
use WP_Error;
// Ensure dependencies for logic file are loaded if not by main loader
use WPAICG\Chat\Admin\AdminSetup;
use WPAICG\Core\AIPKit_Content_Moderator;
use WPAICG\AIPKit_Providers;
use WPAICG\AIPKIT_AI_Settings;
use WPAICG\Core\AIPKit_Instruction_Manager;
use WPAICG\Core\Providers\Google\GoogleSettingsHandler;
use WPAICG\Core\Providers\OpenAI\OpenAIStatefulConversationHelper;
use WPAICG\Chat\Storage\BotSettingsManager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load method logic file (this will in turn load files from the 'process' subdirectory)
require_once __DIR__ . '/fn-process-chat.php';


/**
 * Handles processing stream requests specifically for the 'chat' context.
 */
class SSEChatStreamContextHandler
{
    private $bot_storage;
    private $log_storage;
    private $token_manager;
    private $sse_vector_context_helper;
    private $ai_service_for_helper;

    public function __construct(
        BotStorage $bot_storage,
        LogStorage $log_storage,
        AIPKit_Token_Manager $token_manager,
        ?SSEVectorContextHelper $sse_vector_context_helper
    ) {
        $this->bot_storage = $bot_storage;
        $this->log_storage = $log_storage;
        $this->token_manager = $token_manager;
        $this->sse_vector_context_helper = $sse_vector_context_helper;

        if (!class_exists(ChatAIService::class)) {
            $ai_service_path = WPAICG_PLUGIN_DIR . 'classes/chat/core/ai_service.php';
            if (file_exists($ai_service_path)) {
                require_once $ai_service_path;
            }
        }
        if (class_exists(ChatAIService::class)) {
            $this->ai_service_for_helper = new ChatAIService();
        } else {
            $this->ai_service_for_helper = null;
        }

        $process_chat_dependencies = [
            AdminSetup::class => WPAICG_PLUGIN_DIR . 'classes/chat/admin/chat_admin_setup.php',
            AIPKit_Content_Moderator::class => WPAICG_PLUGIN_DIR . 'classes/core/class-aipkit-content-moderator.php',
            AIPKit_Providers::class => WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_providers.php',
            AIPKIT_AI_Settings::class => WPAICG_PLUGIN_DIR . 'classes/dashboard/class-aipkit_ai_settings.php',
            AIPKit_Instruction_Manager::class => WPAICG_PLUGIN_DIR . 'classes/core/class-aipkit-instruction-manager.php',
            GoogleSettingsHandler::class => WPAICG_PLUGIN_DIR . 'classes/core/providers/google/bootstrap-provider-strategy.php',
            OpenAIStatefulConversationHelper::class => WPAICG_PLUGIN_DIR . 'classes/core/providers/openai/bootstrap-provider-strategy.php',
            BotSettingsManager::class => WPAICG_PLUGIN_DIR . 'classes/chat/storage/class-aipkit_bot_settings_manager.php',
        ];
        foreach ($process_chat_dependencies as $class => $path) {
            if (!class_exists($class) && file_exists($path)) {
                require_once $path;
            }
        }
    }

    public function get_bot_storage(): BotStorage
    {
        return $this->bot_storage;
    }
    public function get_log_storage(): LogStorage
    {
        return $this->log_storage;
    }
    public function get_token_manager(): AIPKit_Token_Manager
    {
        return $this->token_manager;
    }
    public function get_sse_vector_context_helper(): ?SSEVectorContextHelper
    {
        return $this->sse_vector_context_helper;
    }
    public function get_ai_service_for_helper(): ?ChatAIService
    {
        return $this->ai_service_for_helper;
    }


    /**
     * @return mixed[]|\WP_Error
     */
    public function process(array $cached_data, array $get_params)
    {
        // Delegate to the externalized orchestrator function
        return process_chat_logic($this, $cached_data, $get_params);
    }
}
