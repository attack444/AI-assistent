<?php


namespace WPAICG\AIForms\Frontend\Shortcode;

use WPAICG\AIForms\Admin\AIPKit_AI_Form_Admin_Setup;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Validates the shortcode attributes and the existence of the form post.
 *
 * @param array $atts Raw shortcode attributes.
 * @param array &$rendered_form_ids Reference to the array tracking rendered form IDs.
 * @return int|WP_Error Valid form ID on success, WP_Error on failure.
 */
function validate_atts_logic(array $atts, array &$rendered_form_ids)
{
    $atts = shortcode_atts(['id' => 0], $atts, 'aipkit_ai_form');
    $form_id = absint($atts['id']);

    if (empty($form_id)) {
        return new WP_Error('invalid_id', '[AIPKit AI Form Error: Missing or invalid form ID.]');
    }

    if (!class_exists(AIPKit_AI_Form_Admin_Setup::class)) {
        return new WP_Error('internal_error', 'AI Form system component is missing.');
    }
    $form_post = get_post($form_id);
    if (!$form_post || $form_post->post_type !== AIPKit_AI_Form_Admin_Setup::POST_TYPE || $form_post->post_status !== 'publish') {
        return new WP_Error('not_found', sprintf('[AIPKit AI Form Error: Invalid or unpublished Form ID: %d]', $form_id));
    }

    return $form_id;
}
