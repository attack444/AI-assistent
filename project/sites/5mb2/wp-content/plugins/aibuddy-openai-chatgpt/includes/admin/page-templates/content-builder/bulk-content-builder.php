<?php
    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="ai-buddy-container-content" id="bulk-content-builder">
	<div class="section" id="post-bulk-message-box">
		<div class="section-title"><?php echo esc_html__( 'Subject', 'aibuddy-openai-chatgpt' ); ?></div>
		<div class="section-content">
			<div class="section-field">
				<label>
					<span class="section-subtitle"><span><?php echo esc_html__( 'Topics', 'aibuddy-openai-chatgpt' ); ?></span></span>
					<textarea name="topic-bulk-message" placeholder="Enter topic" id="topic-bulk-message"></textarea>
				</label>
			</div>
			<div class="section-field-information">
				<span class="aibuddy-information"></span> <?php echo esc_html__( 'Please ensure that each prompt begins on a separate line.', 'aibuddy-openai-chatgpt' ); ?>
			</div>
			<div class="section-field two-inner-columns">
				<div class="drop-down-boxes-wrapper">
					<div class="section-subtitle"><span><?php echo esc_html__( 'The number of sections generated', 'aibuddy-openai-chatgpt' ); ?></span></div>
					<div class="drop-down-box">
						<div class="drop-down-column">
							<select class="dependent-fields count-post-section ai-buddy-select" title="count-post-section" disabled>
								<option value="2">2</option>
								<option value="3">3</option>
								<option value="4">4</option>
								<option value="6">6</option>
								<option value="8">8</option>
								<option value="10">10</option>
								<option value="12">12</option>
							</select>
						</div>
					</div>
				</div>
				<div class="drop-down-boxes-wrapper">
					<div class="section-subtitle"><span><?php echo esc_html__( 'Paragraphs are needed per section', 'aibuddy-openai-chatgpt' ); ?></span></div>
					<div class="drop-down-box">
						<div class="drop-down-column">
							<select class="dependent-fields count-post-content ai-buddy-select" title="count-post-content" disabled>
								<option value="1">1</option>
								<option value="2">2</option>
								<option value="3">3</option>
								<option value="4">4</option>
								<option value="6">6</option>
							</select>
						</div>
					</div>
				</div>
			</div>
			<div class="section-field">
				<div class="check-box">
					<input type="checkbox" class="checkbox" id="post-title-option" />
					<label for="post-title-option"></label>
					<span class="checkbox-text"><?php echo esc_html__( 'Use Subjects as titles for posts', 'aibuddy-openai-chatgpt' ); ?></span>
				</div>
			</div>
			<div class="drop-down-box-wrapper">
				<div class="section-buttons-box">
					<button type="submit" data-id="button-bulk-post-message" class="ai-buddy-button button-bulk-post-message" disabled><?php echo esc_html__( 'Generate', 'aibuddy-openai-chatgpt' ); ?></button>
					<button class="ai-buddy-button outline button-post-sample" data-sample="Helpful tips"><?php echo esc_html__( 'Try sample', 'aibuddy-openai-chatgpt' ); ?></button>
					<button type="reset" class="ai-buddy-button right-alignment button-post-reset" disabled><?php echo esc_html__( 'Reset', 'aibuddy-openai-chatgpt' ); ?></button>
				</div>
			</div>
			<div class="running-generation" style="display: none;">
				<div class="running-generation-loader">
					<div class="running-generation-sub-title">
						<?php echo esc_html__( 'Generating post', 'aibuddy-openai-chatgpt' ); ?>...
						<span class="running-generation-count">00:00</span>
					</div>
					<div class="progress-bar-box"><span class="progress-bar" role="progressbar" style="width: 0;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></span></div>
				</div>
				<button class="ai-buddy-button outline button-abort"><?php echo esc_html__( 'Cancel', 'aibuddy-openai-chatgpt' ); ?></button>
			</div>
		</div>
	</div>
	<div class="generated-posts"></div>
</div>
