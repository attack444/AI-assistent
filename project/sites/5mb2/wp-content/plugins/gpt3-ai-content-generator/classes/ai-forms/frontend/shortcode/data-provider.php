<?php


namespace WPAICG\AIForms\Frontend\Shortcode;

use WPAICG\AIForms\Storage\AIPKit_AI_Form_Storage;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Logic for retrieving AI Form data.
 *
 * @param AIPKit_AI_Form_Storage $form_storage The instance of the storage class.
 * @param int $form_id The ID of the AI Form post.
 * @return array|WP_Error Form data array or WP_Error on failure.
 */
function get_form_data_logic(AIPKit_AI_Form_Storage $form_storage, int $form_id)
{
    return $form_storage->get_form_data($form_id);
}
