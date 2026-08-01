<?php
    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="ai-buddy-container playground-section">
	<div class="ai-buddy-container-row">
		<div class="ai-buddy-container-content">
			<div class="section">
				<div class="section-title">
					<?php echo esc_html__( 'Playground', 'aibuddy-openai-chatgpt' ); ?>
					<span class="playground-notice"><?php echo esc_html__( 'You can leverage AiBud WP Playground to automate a variety of tasks, such as writing, rewriting, proofreading, translating a text to any language, generating reviews, crafting SEO-optimized content, etc.', 'aibuddy-openai-chatgpt' ); ?></span>
				</div>
				<div class="section-content">
					<div class="section-field query">
						<label>
							<span class="section-subtitle"><span><?php echo esc_html__( 'Command / Query', 'aibuddy-openai-chatgpt' ); ?></span></span>
							<textarea name="playground-query" placeholder="<?php echo esc_html__( 'Kindly enter certain instructions to elicit a reply', 'aibuddy-openai-chatgpt' ); ?>" id="playground-query"></textarea>
						</label>
					</div>
					<div class="section-field answer">
						<label>
							<span class="section-subtitle"><span><?php echo esc_html__( 'Response from AI', 'aibuddy-openai-chatgpt' ); ?></span></span>
							<textarea name="playground-answer" id="playground-answer"></textarea>
						</label>
					</div>
					<div class="section-field submit-section">
						<div class="submit-button-wrapper">
							<button class="submit-button" disabled><i class="aibuddy-loader"></i> <?php echo esc_html__( 'Submit', 'aibuddy-openai-chatgpt' ); ?></button>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="ai-buddy-container-sidebar">
			<?php require AI_BUDDY_PATH . '/includes/admin/page-templates/playground/sidebar.php'; ?>
		</div>
	</div>
</div>
<?php require AI_BUDDY_PATH . '/includes/admin/error-message.php'; ?>
