<?php
    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="ai-buddy-modal-window model-prompts">
	<div class="ai-buddy-modal-window-wrapper">
		<div class="modal-header">
			<div class="section-title"><?php echo esc_html__( 'Commands', 'aibuddy-openai-chatgpt' ); ?></div>
			<div class="modal-close"><span class="aibuddy-close"></span></div>
		</div>
		<div class="modal-body">
			<div class="ai-buddy-container">
				<div class="section">
					<div class="section-content">
						<div class="section-field">
							<div class="section-field-information">
								<span class="aibuddy-information"></span> <?php echo esc_html__( 'The commands serve as a precise inquiry that is transmitted to the AI. The values within the curly brackets will be substituted with the information from the related category.', 'aibuddy-openai-chatgpt' ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="section">
					<div class="section-content">
						<div class="section-field">
							<label class="section-subtitle">
								<span><?php echo esc_html__( 'Command for Title', 'aibuddy-openai-chatgpt' ); ?></span>
								<textarea id="prompt-title-template">Write a title for an article about "{TOPIC}" in {LANGUAGE}. Style: {WRITING_STYLE}. Tone: {WRITING_TONE}. Must be between 40 and 60 characters.</textarea>
							</label>
						</div>
					</div>
				</div>
				<div class="section">
					<div class="section-content">
						<div class="section-field">
							<label class="section-subtitle">
								<span><?php echo esc_html__( 'Command for Sections', 'aibuddy-openai-chatgpt' ); ?></span>
								<textarea id="prompt-section-template">Write {SECTIONS_COUNT} consecutive headings for an article about "{TITLE}", in {LANGUAGE}. Style: {WRITING_STYLE}. Tone: {WRITING_TONE}. Each heading is between 40 and 60 characters. Use Markdown for the headings (## ).</textarea>
							</label>
						</div>
					</div>
				</div>
				<div class="section">
					<div class="section-content">
						<div class="section-field">
							<label class="section-subtitle">
								<span><?php echo esc_html__( 'Command for Content', 'aibuddy-openai-chatgpt' ); ?></span>
								<textarea id="prompt-content-template">Write an article about "{TITLE}" in {LANGUAGE}. The article is organized by the following headings: {SECTIONS}. Write {PARAGRAPHS_COUNT} paragraphs for each heading separately. Use Markdown for formatting. Add an introduction by \"===INTRO: \" and a conclusion by \"===OUTRO: \". Style: {WRITING_STYLE}. Tone: {WRITING_TONE}.</textarea>
							</label>
						</div>
					</div>
				</div>
				<div class="section">
					<div class="section-content">
						<div class="section-field">
							<label class="section-subtitle">
								<span><?php echo esc_html__( 'Command for Excerpt', 'aibuddy-openai-chatgpt' ); ?></span>
								<textarea id="prompt-excerpt-template">Write an SEO optimized excerpt for an article "{TITLE}" in {LANGUAGE}. Style: {WRITING_STYLE}. Tone: {WRITING_TONE}. Current Content of Article: {CONTENT}. The excerpt should be related to title and content. Must be between 120 and 160 characters.</textarea>
							</label>
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
