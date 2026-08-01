<?php

namespace WPAICG;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Define the internationalization functionality.
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.9.15
 * @package    Wp_Ai_Content_Generator
 * @subpackage Wp_Ai_Content_Generator/includes
 * @author     Senol Sahin <senols@gmail.com>
 */
class WP_AI_Content_Generator_i18n
{
    /**
     * Load the plugin text domain for translation.
     * Hooked to 'init'.
     *
     * @since    1.9.15
     * @updated  NEXT_VERSION - Changed hook from 'plugins_loaded' to 'init'
     */
    public function init_hooks() // Method name remains init_hooks, but its action hook changes
    {
        // Intentionally left blank: translations are auto-loaded by WordPress.org.
    }
}
