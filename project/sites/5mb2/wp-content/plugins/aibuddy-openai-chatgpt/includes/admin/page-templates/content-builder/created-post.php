<?php
    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="ai-buddy-modal-window created-post-popup">
	<div class="ai-buddy-modal-window-wrapper">
		<div class="modal-header">
			<div class="section-title"><?php echo esc_html__( 'Success', 'aibuddy-openai-chatgpt' ); ?></div>
			<div class="modal-close"><span class="aibuddy-close"></span></div>
		</div>
		<div class="modal-body">
			<div class="ai-buddy-container">
				<div class="section">
					<div class="section-content">
						<div class="response-message align-center">
							<span class="aibuddy-check-outline response-message-icon"></span>
							<div class="section-subtitle"><?php echo esc_html__( 'Post Successfully Generated!', 'aibuddy-openai-chatgpt' ); ?></div>
							<div class="section-description">
								<?php echo esc_html__( 'The post has been created, but it has been saved as a draft. You can publish it by going to the edit post section.', 'aibuddy-openai-chatgpt' ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="section">
					<div class="section-content">
						<div class="section-button-box section-button-box-full">
							<a href="#" class="ai-buddy-button button-open-post" rel="nofollow" target="_blank"><?php echo esc_html__( 'View Post', 'aibuddy-openai-chatgpt' ); ?></a>
							<a href="#" class="ai-buddy-button right-alignment button-edit-post" rel="nofollow" target="_blank"><?php echo esc_html__( 'Edit Post', 'aibuddy-openai-chatgpt' ); ?></a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="ai-buddy-modal-window-overlay"></div>
</div>
