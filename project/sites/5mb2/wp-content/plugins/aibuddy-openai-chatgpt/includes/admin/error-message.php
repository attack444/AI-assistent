<?php
    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<div class="ai-buddy-modal-window server-error">
	<div class="ai-buddy-modal-window-wrapper">
		<div class="modal-header">
			<div class="section-title"><?php echo esc_html__( 'Error', 'aibuddy-openai-chatgpt' ); ?></div>
			<div class="modal-close"><span class="aibuddy-close"></span></div>
		</div>
		<div class="modal-body">
			<div class="ai-buddy-container">
				<div class="section">
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
