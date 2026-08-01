<?php

namespace WPAICG\Includes\HookRegistrars;

use WPAICG\Chat\Initializer as ChatInitializer;
use WPAICG\AutoGPT\AIPKit_Automated_Task_Cron;
use WPAICG\AIForms\AIPKit_AI_Form_Initializer;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Registers hooks for modules that have their own initializers or self-registering cron jobs.
 */
class Module_Initializer_Hooks_Registrar {

    public static function register() {
        // Chat Initializer
        if (class_exists(ChatInitializer::class) && method_exists(ChatInitializer::class, 'register_hooks')) { // Added method_exists check for safety
            ChatInitializer::register_hooks();
        }

        // Automated Task Cron - Called Statically
        if (self::should_boot_automated_tasks() &&
            class_exists(AIPKit_Automated_Task_Cron::class) &&
            method_exists(AIPKit_Automated_Task_Cron::class, 'init')) {
            AIPKit_Automated_Task_Cron::init(); // Call statically
        }

        // AI Forms Initializer
        if (class_exists(AIPKit_AI_Form_Initializer::class) && method_exists(AIPKit_AI_Form_Initializer::class, 'register_hooks')) {
            AIPKit_AI_Form_Initializer::register_hooks();
        }
    }

    private static function should_boot_automated_tasks(): bool
    {
        if (is_admin() || wp_doing_cron()) {
            return true;
        }

        return defined('WP_CLI') && WP_CLI;
    }
}
