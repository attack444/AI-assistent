<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
global $post;
$general_setting = get_option( 'ai_buddy', array() );
if ( isset( $general_setting['modules']['titles'] ) && $general_setting['modules']['titles'] ) :
	?>
<button type="submit" data-post-id="<?php echo esc_attr( $post->ID ); ?>" class="ai-buddy-button button-full button-post-title-generate"><?php echo esc_html__( 'Generate title', 'aibuddy-openai-chatgpt' ); ?></button>
	<?php
	endif;
if ( isset( $general_setting['modules']['excerpts'] ) && $general_setting['modules']['excerpts'] ) :
	?>
<button type="submit" data-post-id="<?php echo esc_attr( $post->ID ); ?>" class="ai-buddy-button button-full button-post-excerpt-generate"><?php echo esc_html__( 'Generate excerpt', 'aibuddy-openai-chatgpt' ); ?></button>
	<?php
	endif;
if ( isset( $general_setting['modules']['images'] ) && $general_setting['modules']['images'] ) :
	?>
<button type="submit" data-post-id="<?php echo esc_attr( $post->ID ); ?>" class="ai-buddy-button button-full button-post-image-generate"><?php echo esc_html__( 'Generate Image', 'aibuddy-openai-chatgpt' ); ?></button>
	<?php
	endif;
?>
<div class="ai-buddy-modal-window post-generate-popup">
	<div class="ai-buddy-modal-window-wrapper">
		<div class="modal-header">
			<div class="section-title popup-post-title"><?php echo esc_html__( 'Title generator', 'aibuddy-openai-chatgpt' ); ?></div>
			<div class="section-title popup-post-excerpt-title"><?php echo esc_html__( 'Excerpt generator', 'aibuddy-openai-chatgpt' ); ?></div>
			<div class="section-title popup-post-image-title"><?php echo esc_html__( 'Image generator', 'aibuddy-openai-chatgpt' ); ?></div>
			<div class="modal-close"><span class="aibuddy-close"></span></div>
		</div>
		<div class="modal-body">
			<div class="ai-buddy-container">
				<div class="section">
					<div class="section-content generated-post-headers"></div>
				</div>
				<div class="section">
					<div class="section-content">
						<div class="response-message align-center empty-content">
							<span class="aibuddy-documentation response-message-icon"></span>
							<div class="section-subtitle"><?php echo esc_html__( 'Post content is missing', 'aibuddy-openai-chatgpt' ); ?></div>
							<div class="section-description">
								<?php echo esc_html__( 'The content of the post is currently missing. However, titles and excerpts have been generated based on the main content of the post.', 'aibuddy-openai-chatgpt' ); ?>
							</div>
						</div>
						<div class="section-button-box section-button-box-full">
							<button type="button" class="ai-buddy-button gray button-abort-generate"><?php echo esc_html__( 'Cancel', 'aibuddy-openai-chatgpt' ); ?></button>
							<button class="ai-buddy-button right-alignment post-titles-generate" disabled><?php echo esc_html__( 'Apply', 'aibuddy-openai-chatgpt' ); ?></button>
							<button class="ai-buddy-button right-alignment post-excerpts-generate" disabled><?php echo esc_html__( 'Apply', 'aibuddy-openai-chatgpt' ); ?></button>
							<button class="ai-buddy-button right-alignment post-image-generate" disabled><span class="aibuddy-loader button-loader"></span> <?php echo esc_html__( 'Apply', 'aibuddy-openai-chatgpt' ); ?></button>
							<button class="ai-buddy-button right-alignment button-abort-generate post-image-generate-done"><?php echo esc_html__( 'Done', 'aibuddy-openai-chatgpt' ); ?></button>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai_buddy_content_builder' ) );//phpcs:ignore ?>" class="ai-buddy-button right-alignment generate-new-post"><?php echo esc_html__( 'Generate new post', 'aibuddy-openai-chatgpt' ); ?></a>
						</div>
					</div>
				</div>
				<div class="section error-content">
					<div class="section-content">
						<div class="response-message align-center">
							<span class="aibuddy-close-outline response-message-icon"></span>
							<div class="section-subtitle"><?php echo esc_html__( 'An error occurred', 'aibuddy-openai-chatgpt' ); ?></div>
							<div class="section-description">
								<?php echo esc_html__( 'There may be issues with the server or internet connection, or it\'s possible that an incorrect API KEY has been specified.', 'aibuddy-openai-chatgpt' ); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="ai-buddy-modal-window-overlay"></div>
</div>
