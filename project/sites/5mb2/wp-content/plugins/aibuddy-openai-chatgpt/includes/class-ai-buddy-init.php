<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Buddy_Init {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'ai_buddy_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'ai_buddy_admin_scripts_and_styles' ) );
		add_action( 'wp_ajax_ai_buddy_ajax_add_feedback', array( $this, 'ai_buddy_ajax_add_feedback' ) );
    }
	
	/**
	 * Assigning a folder for translations
	 * Domain name text ai_buddy
	 */
	public function ai_buddy_load_text_domain() {
		load_plugin_textdomain( 'aibuddy-openai-chatgpt', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Adding a settings page to the WordPress menu
	 */
	public function ai_buddy_admin_menu() {
		add_menu_page(
			esc_html__( 'AiBud WP', 'aibuddy-openai-chatgpt' ),
			esc_html__( 'AiBud WP', 'aibuddy-openai-chatgpt' ),
			'manage_options',
			'ai_buddy_content_builder',
			array( $this, 'ai_buddy_content_builder' ),
			AI_BUDDY_FILES_PATH . 'assets/images/ai-buddy.png',
			9
		);
		add_submenu_page(
			'ai_buddy_content_builder',
			esc_html__( 'Content Builder', 'aibuddy-openai-chatgpt' ),
			esc_html__( 'Content Builder', 'aibuddy-openai-chatgpt' ),
			'manage_options',
			'ai_buddy_content_builder',
			array( $this, 'ai_buddy_content_builder' )
		);
		add_submenu_page(
			'ai_buddy_content_builder',
			esc_html__( 'Image Generator', 'aibuddy-openai-chatgpt' ),
			esc_html__( 'Image Generator', 'aibuddy-openai-chatgpt' ),
			'manage_options',
			'ai_buddy_image_generator',
			array( $this, 'ai_buddy_image_generator' )
		);
		add_submenu_page(
			'ai_buddy_content_builder',
			esc_html__( 'Playground', 'aibuddy-openai-chatgpt' ),
			esc_html__( 'Playground', 'aibuddy-openai-chatgpt' ),
			'manage_options',
			'ai_buddy_playground',
			array( $this, 'ai_buddy_playground' )
		);
		add_submenu_page(
			'ai_buddy_content_builder',
			esc_html__( 'Settings', 'aibuddy-openai-chatgpt' ),
			esc_html__( 'Settings', 'aibuddy-openai-chatgpt' ),
			'manage_options',
			'ai_buddy_settings',
			array( $this, 'ai_buddy_settings' )
		);
	}

	/**
	 * Connecting files in the admin panel
	 * For Content Builder
	 */
	public function ai_buddy_content_builder() {
		require 'admin/navigation.php';
		require 'admin/page-templates/content-builder/content-builder.php';
	}

	/**
	 * Connecting files in the admin panel
	 * For Image Generator
	 */
	public function ai_buddy_image_generator() {
		require 'admin/navigation.php';
		require 'admin/page-templates/image-generator/image-generator.php';
	}

	/**
	 * Connecting files in the admin panel
	 * For Playground
	 */
	public function ai_buddy_playground() {
		require 'admin/navigation.php';
		require 'admin/page-templates/playground/playground.php';
	}

	/**
	 * Connecting files in the admin panel
	 * For Settings
	 */
	public function ai_buddy_settings() {
		require 'admin/navigation.php';
		require 'admin/page-templates/settings.php';
	}

	/**
	 * Connecting files in the admin panel
	 * For style
	 * For script
	 */
	public function ai_buddy_admin_scripts_and_styles() {
		wp_enqueue_style( 'ai_buddy_admin_styles', AI_BUDDY_FILES_PATH . 'assets/css/app.min.css', array(), AI_BUDDY_VERSION );
		wp_enqueue_style( 'ai_buddy_icons', AI_BUDDY_FILES_PATH . 'assets/icons/style.css', array(), AI_BUDDY_VERSION );
		wp_enqueue_script( 'ai_buddy_admin_scripts', AI_BUDDY_FILES_PATH . 'assets/js/app.min.js', array( 'jquery' ), AI_BUDDY_VERSION, true );

		wp_localize_script(
			'ai_buddy_admin_scripts',
			'ai_buddy_localized_data',
			array(
                'ai_buddy_content_builder' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/ai/generator/completions' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
			    'ai_buddy_create_post' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/wp/posts' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
                'ai_buddy_settings' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/settings' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
                'ai_buddy_status' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/openai/incidents' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
                'ai_buddy_files' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/openai/files' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
                'ai_buddy_image_generator' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/ai/generator/images' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                    'buttons'  => array(
                        'download' => __( 'Download', 'aibuddy-openai-chatgpt' ),
                        'details'  => esc_html__( 'Details', 'aibuddy-openai-chatgpt' ),
                    ),
                ],
                'ai_buddy_create_attachment' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/wp/attachments' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
                'ai_buddy_image_analyzer' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/ai/analyzer/image' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
                'ai_buddy_image_post_generator' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/ai/generator/post_images' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
                'ai_buddy_image_post_attachment' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/wp/attachments' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
                'ai_buddy_generate_titles' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/ai/generator/titles' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
                'ai_buddy_generate_excerpts' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/ai/generator/excerpts' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
                'ai_buddy_generate_excerpts_update' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/wp/posts/update/excerpt' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
                'ai_buddy_generate_tags' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/ai/tags' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
                'ai_buddy_playground' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/ai/generator/completions' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
                'ai_buddy_feedback' => [
                    'nonce'      => wp_create_nonce( 'ai_buddy_ajax_add_feedback' ),
                    'plugin_url' => AI_BUDDY_FILES_PATH,
                ],
                'ai_buddy_feedback_notice' => [
                    'api_url'  => rest_url( '/ai-buddy/v1/ai/notice' ),
                    'rest_url' => rest_url(),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                ],
            )
		);
	}

	public function ai_buddy_ajax_add_feedback() {
		update_option( 'ai_buddy_feedback_added', true );
	}
}

new AI_Buddy_Init();
