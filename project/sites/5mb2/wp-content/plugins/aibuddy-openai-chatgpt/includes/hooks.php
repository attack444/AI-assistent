<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Exit if accessed directly
/** @var \AiBuddy\Plugin $ai_buddy_plugin */
use AiBuddy\Chatbot;
use AiBuddy\Rest;
use AiBuddy\OpenAi;
add_action( 'activate_' . $ai_buddy_plugin->basename, function () use($ai_buddy_plugin) {
    $options = (require "{$ai_buddy_plugin->root_dir}/includes/options.php");
    if ( empty( get_option( 'ai_buddy', '' ) ) ) {
        $ai_buddy_plugin->update_options( $options );
    }
} );
add_action( 'plugins_loaded', array($ai_buddy_plugin, 'init'), 11 );
add_filter(
    'plugin_action_links_' . $ai_buddy_plugin->basename,
    function ( $links, $file, $plugin_info ) use($ai_buddy_plugin) {
        return \AiBuddy\Admin::plugin_actions( $links, $plugin_info, $ai_buddy_plugin->slug );
    },
    10,
    3
);
add_action( 'add_meta_boxes', function () use($ai_buddy_plugin) {
    \AiBuddy\Assistants::add_meta_boxes( $ai_buddy_plugin );
} );
if ( function_exists( 'aibud_fs' ) ) {
}
add_action( 'rest_api_init', function () use($ai_buddy_plugin) {
    $options = $ai_buddy_plugin->options;
    if ( current_user_can( 'administrator' ) ) {
        $ai_buddy_plugin->get( Rest::class )->register_rest_routes( AiBuddy\Plugin::REST_NAMESPACE );
        $ai_buddy_plugin->get( OpenAi\Rest::class )->register_rest_routes( AiBuddy\Plugin::REST_NAMESPACE );
    }
    if ( $options->get( 'modules.chatbot', false ) && class_exists( Chatbot\Rest::class ) ) {
        $ai_buddy_plugin->get( Chatbot\Rest::class )->register_rest_routes( AiBuddy\Plugin::REST_NAMESPACE );
        $ai_buddy_plugin->get( Chatbot\Logs::class )->register_rest_routes( AiBuddy\Plugin::REST_NAMESPACE );
    }
} );
add_action( 'init', function () {
    register_block_type( AI_BUDDY_PATH . '/assets/js/blocks/aibud-text' );
} );
/**
 * Use custom logo in Freemius Opt In Screen.
 */
if ( function_exists( 'aibud_fs' ) ) {
    aibud_fs()->add_filter( 'show_deactivation_feedback_form', '__return_false' );
    if ( !function_exists( 'aibud_opt_in_logo' ) ) {
        function aibud_opt_in_logo() {
            return dirname( AI_BUDDY_FILE ) . '/assets/images/ai-buddy-opt-in-logo.png';
        }

        aibud_fs()->add_filter( 'plugin_icon', 'aibud_opt_in_logo' );
    }
}