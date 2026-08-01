<?php
    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<div class="ai-buddy-comment-helper-wrapper">
    <div class="ai-buddy-comment-helper-open">
        <div class="ai-buddy-comment-helper-button" 
             data-post-id="<?php echo esc_attr(get_the_ID()); ?>">
            <?php echo esc_html($settings['button_text']); ?>
            <span class="ai-buddy-comment-helper-loading"></span>
        </div>
    </div>
    <?php echo wp_kses_post($comment_field); ?>
</div>
