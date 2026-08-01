<?php

/**
 * Plugin Name: AI Bud – AI Content Generator, AI Chatbot, ChatGPT, Gemini, GPT-4o 
 * Plugin URI: https://aibudwp.com/
 * Description: AI Bud is an AI Plugin. AI Content & Image Generation, AI ChatBot, ChatGPT, OpenAI, Perplexity, Gemini, GPT-4o, LLAMA, Mistral
 * Author: WebFactory Ltd
 * Author URI: https://www.webfactoryltd.com/
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: aibuddy-openai-chatgpt
 * Version: 1.9
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( function_exists( 'aibud_fs' ) ) {
    aibud_fs()->set_basename( false, __FILE__ );
} else {
    if ( !function_exists( 'aibud_fs' ) ) {
        function aibud_fs() {
            global $aibud_fs;
            if ( !isset( $aibud_fs ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/vendor/freemius/wordpress-sdk/start.php';
                $aibud_fs = fs_dynamic_init( [
                    'id'             => '12593',
                    'slug'           => 'aibuddy-openai-chatgpt',
                    'premium_slug'   => 'ai-buddy-chatgpt-openai-pro',
                    'type'           => 'plugin',
                    'public_key'     => 'pk_95c96ccb2de33633c563097c2604c',
                    'is_premium'     => false,
                    'has_addons'     => false,
                    'has_paid_plans' => true,
                    'menu'           => [
                        'slug'    => 'ai_buddy_content_builder',
                        'support' => false,
                        'contact' => true,
                    ],
                    'is_live'        => true,
                ] );
            }
            return $aibud_fs;
        }

        // Init Freemius.
        aibud_fs();
        // Signal that SDK was initiated.
        do_action( 'aibud_fs_loaded' );
    }
    require_once __DIR__ . '/vendor/autoload.php';
    define( 'AI_BUDDY_VERSION', '1.8.6' );
    define( 'AI_BUDDY_PATH', __DIR__ );
    define( 'AI_BUDDY_FILE', __FILE__ );
    define( 'AI_BUDDY_SLUG', 'ai_buddy' );
    define( 'AI_BUDDY_FILES_PATH', plugin_dir_url( __FILE__ ) );
    $ai_buddy_plugin = new AiBuddy\Plugin(AI_BUDDY_SLUG, __FILE__);
    require __DIR__ . '/includes/hooks.php';
    require __DIR__ . '/includes/class-ai-buddy.php';
}