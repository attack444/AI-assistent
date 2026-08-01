<?php


namespace WPAICG\ContentWriter\Ajax;

use WPAICG\ContentWriter\AIPKit_Content_Writer_Template_Manager;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
* Handles AJAX actions for Content Writer templates.
* Delegates logic to namespaced functions.
*/
class AIPKit_Content_Writer_Template_Ajax_Handler extends AIPKit_Content_Writer_Base_Ajax_Action
{
    private $template_manager;
    public const NONCE_ACTION = 'aipkit_content_writer_template_nonce';

    public function __construct()
    {
        parent::__construct();
        if (class_exists(AIPKit_Content_Writer_Template_Manager::class)) {
            $this->template_manager = new AIPKit_Content_Writer_Template_Manager();
        }
    }

    // Public getter for the externalized logic to access the dependency
    public function get_template_manager(): ?AIPKit_Content_Writer_Template_Manager
    {
        return $this->template_manager;
    }

    /**
    * AJAX: Saves or updates a template.
    */
    public function ajax_save_template()
    {
        $permission_check = $this->check_module_access_permissions('content-writer', self::NONCE_ACTION);
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/template/ajax-save-template.php';
        \WPAICG\ContentWriter\Ajax\Template\ajax_save_template_logic($this);
    }

    /**
    * AJAX: Deletes a template.
    */
    public function ajax_delete_template()
    {
        $permission_check = $this->check_module_access_permissions('content-writer', self::NONCE_ACTION);
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/template/ajax-delete-template.php';
        \WPAICG\ContentWriter\Ajax\Template\ajax_delete_template_logic($this);
    }

    /**
    * AJAX: Lists all templates for the current user.
    */
    public function ajax_list_templates()
    {
        $permission_check = $this->check_module_access_permissions('content-writer', self::NONCE_ACTION);
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/template/ajax-list-templates.php';
        \WPAICG\ContentWriter\Ajax\Template\ajax_list_templates_logic($this);
    }

    /**
    * AJAX: Resets starter templates for the current user.
    */
    public function ajax_reset_starter_templates()
    {
        $permission_check = $this->check_module_access_permissions('content-writer', self::NONCE_ACTION);
        if (is_wp_error($permission_check)) {
            $this->send_wp_error($permission_check);
            return;
        }
        require_once __DIR__ . '/template/ajax-reset-starter-templates.php';
        \WPAICG\ContentWriter\Ajax\Template\ajax_reset_starter_templates_logic($this);
    }
}
