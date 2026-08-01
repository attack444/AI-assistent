<?php

namespace WPAICG\AIForms\Storage;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load the new method logic files
require_once __DIR__ . '/methods/methods.php';

/**
 * Manages storage and retrieval of AI Form CPTs and their settings (meta).
 * This class now acts as a facade, delegating its methods to namespaced functions.
 */
class AIPKit_AI_Form_Storage
{
    /**
     * Retrieves AI Form data and settings.
     *
     * @param int $form_id The ID of the AI Form post.
     * @return array|\WP_Error Form data array or WP_Error if not found or invalid.
     */
    public function get_form_data(int $form_id)
    {
        return Methods\get_form_data_logic($this, $form_id);
    }

    /**
     * Saves AI Form settings.
     *
     * @param int $form_id The ID of the form CPT.
     * @param array $settings An array containing settings.
     * @return bool True on success, false on failure.
     */
    public function save_form_settings(int $form_id, array $settings): bool
    {
        return Methods\save_form_settings_logic($this, $form_id, $settings);
    }

    /**
     * Creates a new AI Form CPT.
     *
     * @param string $title The title of the form.
     * @param array $settings Optional settings to save.
     * @return int|\WP_Error The new post ID on success, or WP_Error on failure.
     */
    public function create_form(string $title, array $settings = [])
    {
        return Methods\create_form_logic($this, $title, $settings);
    }

    /**
     * Deletes an AI Form CPT.
     *
     * @param int $form_id The ID of the form to delete.
     * @return bool True on success, false on failure.
     */
    public function delete_form(int $form_id): bool
    {
        return Methods\delete_form_logic($this, $form_id);
    }

    /**
     * Retrieves a list of all AI Forms.
     *
     * @param array $args WP_Query arguments.
     * @return array List of form objects (ID, title, shortcode).
     */
    public function get_forms_list(array $args = []): array
    {
        return Methods\get_forms_list_logic($this, $args);
    }

}
