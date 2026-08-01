<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
?>

<form id="ai-buddy-comment-helper">
    <div class="ai-buddy-container">
        <div class="ai-buddy-container-row">
            <div class="ai-buddy-container-content">
                <div class="section">
                    <div class="header">
                        <div class="check-box right-side">
                            <div class="checkbox-text"><?php 
echo esc_html__( 'Comment Helper', 'aibuddy-openai-chatgpt' );
?></div>
                            <input type="checkbox" class="checkbox" name="ai-buddy-comment-helper-status" id="ai-buddy-comment-helper-status" <?php 
echo ( isset( $comment_settings['status'] ) && $comment_settings['status'] === true ? 'checked' : '' );
?> />
                            <label for="ai-buddy-comment-helper-status"></label>
                        </div>
                    </div>
                </div>

                <?php 
?>
                    <div class="api-key-settings ai-buddy-pro-notice">
                        <div class="api-key-settings-icon ai-buddy-pro-notice-icon">
                            <img src="<?php 
echo esc_url( AI_BUDDY_FILES_PATH . 'assets/images/premium.svg' );
?>" width="40" height="40" alt="AiBud WP Plugin get API Key" />
                        </div>
                        <div class="api-key-settings-content">
                            <span><?php 
echo esc_html__( 'Unlock this feature with Pro!', 'aibuddy-openai-chatgpt' );
?></span>
                            <span class="api-key-validation">
                                    <?php 
echo esc_html__( ' Upgrade to Pro for immediate access and elevate AI experience.', 'aibuddy-openai-chatgpt' );
?>
                            </span>
                        </div>
                        <div class="api-key-settings-button">
                            <a href="<?php 
echo esc_url( admin_url( 'admin.php?page=ai_buddy_content_builder-pricing' ) );
//phpcs:ignore
?>" class="ai-buddy-button"><?php 
echo esc_html__( 'Upgrade to Pro', 'aibuddy-openai-chatgpt' );
?></a>
                        </div>
                    </div>
                    <div class="section">
                        <div class="section-title ai-buddy-pro-notice-subtitle"><?php 
echo esc_html__( 'How Comment Helper Works?', 'aibuddy-openai-chatgpt' );
?></div>
                        <iframe class="ai-buddy-pro-notice-video" src="https://www.youtube.com/embed/cCKggPgneco" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                <?php 
?>
            </div>
            <div class="ai-buddy-container-sidebar">
                <div class="section ai-buddy-accordion-section">
                    <?php 
?>
                        <a href="javascript:void(0)" class="ai-buddy-button button-full" id="ai-buddy-comment-helper-save"><?php 
echo esc_html__( 'Save', 'aibuddy-openai-chatgpt' );
?></a>
                    <?php 
?>
                </div>
            </div>
        </div>
    </div>
    <div class="plugin-settings-request">
        <span class="successful-request"><span class="settings-request-icon aibuddy-solid-check"></span> <?php 
echo esc_html__( 'Changes successfully saved', 'aibuddy-openai-chatgpt' );
?></span>
        <span class="error-request"><span class="settings-request-icon aibuddy-solid-cancel"></span> <?php 
echo esc_html__( 'Something went wrong', 'aibuddy-openai-chatgpt' );
?></span>
    </div>
</form>
