<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Exit if accessed directly //phcpcs:ignore because no nonce needed since it can be linked to directly
$current_page = ( isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '' ); //phpcs:ignore
?>
<div class="ai-buddy-navigation">
    <div class="ai-buddy-navigation-logo">
        <div class="ai-buddy-navigation-logo-image">
            <img src="<?php 
echo esc_url( AI_BUDDY_FILES_PATH . 'assets/images/ai-buddy.png' );
?>" width="40" height="40" alt="<?php 
echo esc_attr__( 'AiBud WP Plugin with Artificial Intelligence for WordPress', 'aibuddy-openai-chatgpt' );
?>" />
        </div>
        <div class="ai-buddy-navigation-logo-text">
            <?php 
echo esc_html__( 'AiBud WP Plugin', 'aibuddy-openai-chatgpt' );
?>
            <div class="plugin-version">
                <?php 
printf( '%s%s', esc_html__( 'Version: ', 'aibuddy-openai-chatgpt' ), esc_html( AI_BUDDY_VERSION ) );
?>
            </div>
        </div>
    </div>
    <div class="ai-buddy-navigation-list">
        <div class="ai-buddy-navigation-list-item <?php 
echo esc_attr( ( $current_page === 'ai_buddy_content_builder' ? 'active' : '' ) );
?>">
            <a href="<?php 
echo esc_url( admin_url( 'admin.php?page=ai_buddy_content_builder' ) );
?>" rel="nofollow">
                <span class="aibuddy-edit"></span> 
                <?php 
echo esc_html__( 'Content builder', 'aibuddy-openai-chatgpt' );
?>
            </a>
        </div>
        <div class="ai-buddy-navigation-list-item <?php 
echo esc_attr( ( $current_page === 'ai_buddy_image_generator' ? 'active' : '' ) );
?>">
            <a href="<?php 
echo esc_url( admin_url( 'admin.php?page=ai_buddy_image_generator' ) );
?>" rel="nofollow">
                <span class="aibuddy-image"></span> 
                <?php 
echo esc_html__( 'Image generator', 'aibuddy-openai-chatgpt' );
?>
            </a>
        </div>
        <div class="ai-buddy-navigation-list-item <?php 
echo esc_attr( ( $current_page === 'ai_buddy_playground' ? 'active' : '' ) );
?>">
            <a href="<?php 
echo esc_url( admin_url( 'admin.php?page=ai_buddy_playground' ) );
?>" rel="nofollow">
                <span class="aibuddy-cubes"></span> 
                <?php 
echo esc_html__( 'Playground', 'aibuddy-openai-chatgpt' );
?>
            </a>
        </div>
        <?php 
?>
            <div class="ai-buddy-navigation-list-item <?php 
echo esc_attr( ( $current_page === 'ai_buddy_chatbot' ? 'active' : '' ) );
?>">
                <a href="<?php 
echo esc_url( admin_url( 'admin.php?page=ai_buddy_content_builder-pricing' ) );
?>" rel="nofollow">
                    <span class="aibuddy-chat"></span> 
                    <?php 
echo esc_html__( 'Chatbot', 'aibuddy-openai-chatgpt' );
?><var><?php 
echo esc_html__( 'PRO', 'aibuddy-openai-chatgpt' );
?></var></a></div>
        <?php 
?>
        <div class="ai-buddy-navigation-list-item <?php 
echo esc_attr( ( $current_page === 'ai_buddy_settings' ? 'active' : '' ) );
?>">
            <a href="<?php 
echo esc_url( admin_url( 'admin.php?page=ai_buddy_settings' ) );
?>" rel="nofollow">
                <span class="aibuddy-settings"></span> 
                <?php 
echo esc_html__( 'Settings', 'aibuddy-openai-chatgpt' );
?>
            </a>
        </div>
    </div>
    <div class="ai-buddy-navigation-additions">
        <a href="#" rel="nofollow" class="notifications-status"><span class="aibuddy-info"></span> <?php 
echo esc_html__( 'Open AI status', 'aibuddy-openai-chatgpt' );
?></a>
        <div class="ai-status">
            <div class="ai-status-content-wrapper">
                <h3><?php 
echo esc_html__( 'Open AI status', 'aibuddy-openai-chatgpt' );
?></h3>
                <div class="ai-status-content-inside"></div>
            </div>
        </div>
        <a href="#" rel="nofollow" class="additions-menu"><span class="aibuddy-bullets"></span><span class="aibuddy-close-big"></span></a>
        <div class="support-menu">
            <ul>
                <li><a href="https://aibudwp.com/docs" target="_blank" rel="nofollow"><span class="aibuddy-documentation"></span> <?php 
echo esc_html__( 'Documentation', 'aibuddy-openai-chatgpt' );
?></a></li>
                <li><a href="https://aibudwp.com/contact/" target="_blank" rel="nofollow"><span class="aibuddy-support"></span> <?php 
echo esc_html__( 'Support', 'aibuddy-openai-chatgpt' );
?></a></li>
                <?php 
if ( !get_option( 'ai_buddy_feedback_added', false ) ) {
    ?>
                    <li class="ai-buddy-feedback-button"><a href="#"><span class="aibuddy-feedback"></span> <?php 
    echo esc_html__( 'Feedback', 'aibuddy-openai-chatgpt' );
    ?></a></li>
                <?php 
}
?>
            </ul>
        </div>
    </div>
</div>
<?php 
require AI_BUDDY_PATH . '/includes/admin/api-key-alert.php';
require AI_BUDDY_PATH . '/includes/admin/feedback.php';