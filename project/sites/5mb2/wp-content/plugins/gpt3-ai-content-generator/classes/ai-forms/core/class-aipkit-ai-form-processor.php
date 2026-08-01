<?php


namespace WPAICG\AIForms\Core;

use WPAICG\Core\AIPKit_AI_Caller;
use WPAICG\AIForms\Storage\AIPKit_AI_Form_Storage;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load the new method logic files
require_once __DIR__ . '/ajax/ajax-upload-and-parse-file.php';
require_once __DIR__ . '/ajax/ajax-save-as-post.php';

/**
 * Handles processing AI Form submissions, interacting with AI services,
 * and returning results.
 * This class now acts as a facade, delegating its logic.
 */
class AIPKit_AI_Form_Processor
{
    public $ai_caller;
    public $form_storage;

    public function __construct()
    {
        if (class_exists(\WPAICG\Core\AIPKit_AI_Caller::class)) {
            $this->ai_caller = new \WPAICG\Core\AIPKit_AI_Caller();
        }
        if (class_exists(\WPAICG\AIForms\Storage\AIPKit_AI_Form_Storage::class)) {
            $this->form_storage = new \WPAICG\AIForms\Storage\AIPKit_AI_Form_Storage();
        }
    }

    /**
     * Handles an AJAX form submission by delegating to the orchestrator logic.
     * REMOVED as AI Forms now only use the SSE stream handler.
     */
    // public function ajax_handle_submission()

    /**
    * AJAX handler for uploading a file from an AI Form, parsing it, and returning its text content.
    * This is a Pro feature.
    * @since 2.1
    */
    public function ajax_upload_and_parse_file()
    {
        Ajax\upload_and_parse_file_logic($this);
    }

    /**
     * AJAX handler for saving generated form content as a post.
     * @since 2.1
     */
    public function ajax_save_as_post()
    {
        Ajax\save_as_post_logic($this);
    }
}
