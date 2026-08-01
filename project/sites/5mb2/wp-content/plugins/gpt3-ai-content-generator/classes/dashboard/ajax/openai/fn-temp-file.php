<?php


namespace WPAICG\Dashboard\Ajax\OpenAI;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates a temporary file from a string of content.
 *
 * @param string $content_string The content to write to the file.
 * @param string $filename_prefix Prefix for the temporary filename.
 * @return string|WP_Error The path to the temporary file or WP_Error on failure.
 */
function _aipkit_openai_vs_files_create_temp_file_from_string(string $content_string, string $filename_prefix = 'aipkit-content')
{
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();
    global $wp_filesystem;

    if (is_wp_error($wp_filesystem)) {
        return $wp_filesystem;
    }
    if (!$wp_filesystem) {
        return new WP_Error('filesystem_init_failed', __('Could not initialize the WordPress filesystem.', 'gpt3-ai-content-generator'));
    }

    $temp_file_path = wp_tempnam($filename_prefix, get_temp_dir());
    if ($temp_file_path === false) {
        return new WP_Error('temp_file_creation_failed', __('Could not create temporary file for content.', 'gpt3-ai-content-generator'));
    }

    $final_temp_file_path = dirname($temp_file_path) . '/' . basename($temp_file_path, '.tmp') . '.txt';
    
    if ($wp_filesystem->move($temp_file_path, $final_temp_file_path, true)) { // true to overwrite
        $temp_file_path = $final_temp_file_path;
    } else {
        // If move fails, clean up original and return error
        $wp_filesystem->delete($temp_file_path);
        return new WP_Error('temp_file_rename_failed', __('Could not rename temporary file.', 'gpt3-ai-content-generator'));
    }

    // Use WP_Filesystem::put_contents() for writing
    $bytes_written = $wp_filesystem->put_contents($temp_file_path, $content_string);
    if ($bytes_written === false) {
        $wp_filesystem->delete($temp_file_path);
        return new WP_Error('temp_file_write_failed', __('Could not write content to temporary file.', 'gpt3-ai-content-generator'));
    }
    return $temp_file_path;
}
