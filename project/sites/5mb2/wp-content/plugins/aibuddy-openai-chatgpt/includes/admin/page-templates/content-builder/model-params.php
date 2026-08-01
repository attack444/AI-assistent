<?php
    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="ai-buddy-modal-window model-params-popup">
	<div class="ai-buddy-modal-window-wrapper">
		<div class="modal-header">
			<div class="section-title"><?php echo esc_html__( 'AI Options', 'aibuddy-openai-chatgpt' ); ?></div>
			<div class="modal-close"><span class="aibuddy-close"></span></div>
		</div>
		<div class="modal-body">
			<div class="ai-buddy-container">
				<div class="section">
					<div class="section-content">
						<div class="section-field">
							<label class="section-subtitle">
								<span><?php echo esc_html__( 'Temperature:', 'aibuddy-openai-chatgpt' ); ?></span>
								<input type="number" placeholder="0.6" id="model-temperature" value="0.6" min="0.1" max="1" step="0.1" />
							</label>
						</div>
						<div class="section-field-information" style="margin-top: -10px;">
							<span class="aibuddy-information"></span> <?php echo esc_html__( 'The range is from 0.1 to 1, where a larger value indicates that the model will be more inclined to take risks.', 'aibuddy-openai-chatgpt' ); ?>
						</div>
					</div>
				</div>
				<div class="section">
					<div class="section-content">
						<div class="section-field">
							<label class="section-subtitle">
								<span><?php echo esc_html__( 'Maximum number of Tokens:', 'aibuddy-openai-chatgpt' ); ?></span>
								<input type="number" value="2048" min="16" max="16000" class="model-max-tokens" />
							</label>
						</div>
						<div class="section-field-information" style="margin-top: -10px;">
							<span class="aibuddy-information"></span> <?php echo esc_html__( 'The range for the values is from 16 to 2048. If the value is higher, the model will produce a greater amount of content.', 'aibuddy-openai-chatgpt' ); ?>
						</div>
					</div>
				</div>
				<div class="section">
					<div class="section-content">
						<div class="section-button-box section-button-box-full">
							<button type="button" class="ai-buddy-button gray button-model-params-close"><?php echo esc_html__( 'Cancel', 'aibuddy-openai-chatgpt' ); ?></button>
							<button class="ai-buddy-button right-alignment button-model-params-close"><?php echo esc_html__( 'Apply', 'aibuddy-openai-chatgpt' ); ?></button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="ai-buddy-modal-window-overlay"></div>
</div>
