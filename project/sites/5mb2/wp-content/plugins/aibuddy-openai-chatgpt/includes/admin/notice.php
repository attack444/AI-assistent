<?php
    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="ai-buddy-notice notice">
    <div class="ai-buddy-notice-img">
        <img src="<?php echo esc_url(AI_BUDDY_FILES_PATH . 'assets/images/notice-logo.png'); ?>" alt="Notice"
             class="ai-buddy-notice-logo"/>
    </div>
    <div class="ai-buddy-notice-content">
        <div class="ai-buddy-notice-content-text">
            <?php printf(
            /* translators: %s: Plugin name "AiBud WP plugin" */
                esc_html__("Good News! You’ve created more than 20 content by using %s. Maybe you can give us a nice review? It would motivate us a lot! Is that fair?", "aibuddy-openai-chatgpt"),
                '<strong>' . esc_html__("AiBud WP plugin", "aibuddy-openai-chatgpt") . '</strong>') ?>
        </div>
        <div class="ai-buddy-notice-action-container">
            <a href="https://wordpress.org/support/plugin/aibuddy-openai-chatgpt/reviews/?filter=5#new-post" target='_blank' class="ai-buddy-notice-action"
               data-choice="agree"><?php echo esc_html__("Yes, that’s fair!", "aibuddy-openai-chatgpt"); ?></a>
            <a href="javascript:void(0)" class="ai-buddy-notice-action ai-buddy-notice-feedback"
               data-choice="later"><?php echo esc_html__("Ask me later", "aibuddy-openai-chatgpt"); ?></a>
            <a href="javascript:void(0)" class="ai-buddy-notice-action ai-buddy-notice-feedback"
               data-choice="never"><?php echo esc_html__("I already did", "aibuddy-openai-chatgpt"); ?></a>
            <a href="javascript:void(0)" class="ai-buddy-notice-action ai-buddy-notice-feedback"
               data-choice="never"><?php echo esc_html__("I’m just taking, not giving", "aibuddy-openai-chatgpt"); ?></a>
        </div>
    </div>
</div>
