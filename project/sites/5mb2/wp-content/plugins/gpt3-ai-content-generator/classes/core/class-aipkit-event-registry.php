<?php

namespace WPAICG\Core;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Canonical event definitions for the Universal Event Webhooks foundation.
 */
class AIPKit_Event_Registry
{
    public const SCHEMA_VERSION = '2026-05-21';

    /**
     * Returns the current v1 event definitions.
     *
     * @return array<string, array<string, string>>
     */
    public static function get_definitions(): array
    {
        return [
            'chatbot.session_started' => [
                'module' => 'chatbot',
                'category' => 'chatbot',
                'label' => 'Chat session started',
            ],
            'chatbot.user_message_submitted' => [
                'module' => 'chatbot',
                'category' => 'chatbot',
                'label' => 'Chat user message submitted',
            ],
            'chatbot.response_generated' => [
                'module' => 'chatbot',
                'category' => 'chatbot',
                'label' => 'Chat response generated',
            ],
            'chatbot.fb_submitted' => [
                'module' => 'chatbot',
                'category' => 'chatbot',
                'label' => 'Chat feedback submitted',
            ],
            'chatbot.form_submitted' => [
                'module' => 'chatbot',
                'category' => 'chatbot',
                'label' => 'Chatbot form submitted',
            ],
            'content.generated' => [
                'module' => 'content_writer',
                'category' => 'content',
                'label' => 'Content generated',
            ],
            'task.item_completed' => [
                'module' => 'automated_tasks',
                'category' => 'tasks',
                'label' => 'Task queue item completed',
            ],
            'form.submitted' => [
                'module' => 'ai_forms',
                'category' => 'forms',
                'label' => 'AI form submitted',
            ],
            'image.generated' => [
                'module' => 'image_generator',
                'category' => 'images',
                'label' => 'Image generated',
            ],
            'kb.source_indexed' => [
                'module' => 'knowledge_base',
                'category' => 'knowledge_base',
                'label' => 'KB source indexed',
            ],
        ];
    }

    /**
     * Returns whether the given event is registered.
     *
     * @param string $event_name
     * @return bool
     */
    public static function has_event(string $event_name): bool
    {
        $definitions = self::get_definitions();
        return isset($definitions[$event_name]);
    }

    /**
     * Returns a single event definition or null if unsupported.
     *
     * @param string $event_name
     * @return array<string, string>|null
     */
    public static function get_definition(string $event_name): ?array
    {
        $definitions = self::get_definitions();
        return $definitions[$event_name] ?? null;
    }

    /**
     * Returns the current schema version string.
     *
     * @return string
     */
    public static function get_schema_version(): string
    {
        return self::SCHEMA_VERSION;
    }
}
