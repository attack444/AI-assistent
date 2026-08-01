<?php
    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="ai-buddy-container ai-buddy-image-generator">
	<div class="ai-buddy-container-row">
		<div class="ai-buddy-container-content">
			<div class="section form-section">
				<div class="section-title"><?php echo esc_html__( 'Instructions', 'aibuddy-openai-chatgpt' ); ?></div>
				<div class="section-content">
					<div class="section-field">
						<form id="image-generator-form">
							<div class="image-prompt-wrapper">
								<div class="image-prompt-subtitle"><?php echo esc_html__( 'Command', 'aibuddy-openai-chatgpt' ); ?></div>
								<textarea name="image-prompt" placeholder="<?php echo esc_attr__( 'Please provide a command to create pictures', 'aibuddy-openai-chatgpt' ); ?>" id="image-prompt"></textarea>
							</div>
							<div class="image-count-wrapper">
								<div class="image-count-subtitle"><?php echo esc_html__( 'The number of images that are being generated', 'aibuddy-openai-chatgpt' ); ?></div>
								<select name="image-count" id="image-count" class="ai-buddy-select">
									<option value="1">1</option>
									<option value="2">2</option>
									<option value="3">3</option>
									<option value="6">6</option>
									<option value="9">9</option>
								</select>
							</div>
							<div class="generate-row">
								<div class="estimated-price-wrapper">
									<?php echo esc_html__( 'Estimated Price:', 'aibuddy-openai-chatgpt' ); ?>
									<span class="estimated-price"> $0.02</span>
								</div>
								<div class="image-generate-wrapper">
									<button href="#" class="image-generate-button" disabled>
										<i class="aibuddy-loader"></i><?php echo esc_html__( 'Generate', 'aibuddy-openai-chatgpt' ); ?>
									</button>
									<button class="ai-buddy-button outline button-images-abort"><?php echo esc_html__( 'Cancel', 'aibuddy-openai-chatgpt' ); ?></button>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
			<div class="section result-section">
				<div class="result-section-wrapper">
					<div class="section-title"><?php echo esc_html__( 'Result', 'aibuddy-openai-chatgpt' ); ?></div>
					<div class="result-count">
						<span class="result-number">0 </span><?php echo esc_html__( 'Images', 'aibuddy-openai-chatgpt' ); ?></div>
					<div class="section-content">
						<div class="result-wrapper"></div>
					</div>
				</div>
			</div>
		</div>
		<div class="ai-buddy-container-sidebar">
			<?php require AI_BUDDY_PATH . '/includes/admin/page-templates/image-generator/sidebar.php'; ?>
		</div>
	</div>
</div>
<?php
	require AI_BUDDY_PATH . '/includes/admin/page-templates/image-generator/modal-popup.php';
	require AI_BUDDY_PATH . '/includes/admin/error-message.php';
?>
