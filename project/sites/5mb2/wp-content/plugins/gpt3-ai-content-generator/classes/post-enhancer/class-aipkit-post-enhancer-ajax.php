<?php

namespace WPAICG\PostEnhancer;

if (!defined('ABSPATH')) {
    exit;
}

use WPAICG\PostEnhancer\Ajax\Actions;

// Load the base class and all action classes
require_once __DIR__ . '/ajax/base/abstract-enhancer-ajax-action.php';
require_once __DIR__ . '/ajax/base/enhancer-shared-utils.php';
require_once __DIR__ . '/ajax/actions/generate-title.php';
require_once __DIR__ . '/ajax/actions/generate-excerpt.php';
require_once __DIR__ . '/ajax/actions/generate-meta.php';
require_once __DIR__ . '/ajax/actions/generate-tags.php';
require_once __DIR__ . '/ajax/actions/update-title.php';
require_once __DIR__ . '/ajax/actions/update-excerpt.php';
require_once __DIR__ . '/ajax/actions/update-meta.php';
require_once __DIR__ . '/ajax/actions/update-tags.php';
require_once __DIR__ . '/ajax/actions/bulk-process-single-field.php';
require_once __DIR__ . '/ajax/actions/bulk-update-seo-slug.php';
require_once __DIR__ . '/ajax/actions/process-text.php';

/**
 * Main AJAX handler for the Content Enhancer module.
 * Instantiates and dispatches requests to dedicated action classes.
 */
class AjaxHandler
{
    private $generate_title_handler;
    private $generate_excerpt_handler;
    private $generate_meta_handler;
    private $generate_tags_handler;
    private $update_title_handler;
    private $update_excerpt_handler;
    private $update_meta_handler;
    private $update_tags_handler;
    private $bulk_process_single_field_handler;
    private $bulk_update_seo_slug_handler;
    private $process_text_handler;

    public function __construct()
    {
        $this->generate_title_handler = new Actions\AIPKit_PostEnhancer_Generate_Title();
        $this->generate_excerpt_handler = new Actions\AIPKit_PostEnhancer_Generate_Excerpt();
        $this->generate_meta_handler = new Actions\AIPKit_PostEnhancer_Generate_Meta();
        $this->generate_tags_handler = new Actions\AIPKit_PostEnhancer_Generate_Tags();
        $this->update_title_handler = new Actions\AIPKit_PostEnhancer_Update_Title();
        $this->update_excerpt_handler = new Actions\AIPKit_PostEnhancer_Update_Excerpt();
        $this->update_meta_handler = new Actions\AIPKit_PostEnhancer_Update_Meta();
        $this->update_tags_handler = new Actions\AIPKit_PostEnhancer_Update_Tags();
        $this->bulk_process_single_field_handler = new Actions\AIPKit_PostEnhancer_Bulk_Process_Single_Field();
        $this->bulk_update_seo_slug_handler = new Actions\AIPKit_PostEnhancer_Bulk_Update_SEO_Slug();
        $this->process_text_handler = new Actions\AIPKit_PostEnhancer_Process_Text();
    }

    public function generate_title_suggestions()
    {
        $this->generate_title_handler->handle();
    }
    public function generate_excerpt_suggestions()
    {
        $this->generate_excerpt_handler->handle();
    }
    public function generate_meta_suggestions()
    {
        $this->generate_meta_handler->handle();
    }
    public function generate_tags_suggestions()
    {
        $this->generate_tags_handler->handle();
    }
    public function update_post_title()
    {
        $this->update_title_handler->handle();
    }
    public function update_post_excerpt()
    {
        $this->update_excerpt_handler->handle();
    }
    public function update_post_meta_desc()
    {
        $this->update_meta_handler->handle();
    }
    public function update_post_tags()
    {
        $this->update_tags_handler->handle();
    }

    public function ajax_bulk_process_single_field()
    {
        $this->bulk_process_single_field_handler->handle();
    }

    /**
     * AJAX handler for updating SEO slug of a post.
     */
    public function ajax_bulk_update_seo_slug()
    {
        $this->bulk_update_seo_slug_handler->handle();
    }

    /**
     * AJAX handler for processing text selected in an editor.
     */
    public function ajax_process_enhancer_text()
    {
        $this->process_text_handler->handle();
    }
}
