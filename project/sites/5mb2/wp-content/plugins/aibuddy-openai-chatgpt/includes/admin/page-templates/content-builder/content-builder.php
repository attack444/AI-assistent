<?php
    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<form id="topic-form">
	<div class="ai-buddy-container">
		<div class="ai-buddy-container-row">
			<div class="ai-buddy-container-content" id="content-builder">
				<div class="section" id="post-message-box">
					<div class="section-title"><?php echo esc_html__( 'Subject', 'aibuddy-openai-chatgpt' ); ?></div>
					<div class="section-content">
						<div class="section-field">
							<textarea name="topic-message" placeholder="Please provide the subject of your post" id="topic-message"></textarea>
						</div>
						<div class="drop-down-box-wrapper">
							<div class="section-buttons-box">
								<button type="submit" data-id="button-post-message" class="ai-buddy-button button-post-message" disabled><?php echo esc_html__( 'Generate all', 'aibuddy-openai-chatgpt' ); ?></button>
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
								<div class="progress-bar-box"><span class="progress-bar" role="progressbar" style="width: 0;"></span></div>
							</div>
							<button class="ai-buddy-button outline button-abort"><?php echo esc_html__( 'Cancel', 'aibuddy-openai-chatgpt' ); ?></button>
						</div>
					</div>
				</div>
				<div class="section" id="post-title-box">
					<div class="section-title"><?php echo esc_html__( 'Title', 'aibuddy-openai-chatgpt' ); ?></div>
					<div class="section-content">
						<div class="section-field">
							<input type="text" id="post-title" name="post-title" value="" placeholder="Please provide the title of your post" />
						</div>
					</div>
				</div>
				<div class="section" id="post-section-box">
					<div class="section-title"><?php echo esc_html__( 'Sections', 'aibuddy-openai-chatgpt' ); ?></div>
					<div class="section-content">
						<div class="drop-down-box-wrapper">
							<div class="section-subtitle"><span><?php echo esc_html__( 'The number of sections that are being generated', 'aibuddy-openai-chatgpt' ); ?></span></div>
							<div class="drop-down-box">
								<div class="drop-down-column">
									<select id="count-post-section" class="ai-buddy-select" title="count-post-section">
										<option value="1">1</option>
										<option value="2">2</option>
										<option value="3">3</option>
										<option value="4">4</option>
										<option value="6">6</option>
										<option value="8">8</option>
										<option value="10">10</option>
										<option value="12">12</option>
									</select>
								</div>
								<div class="drop-down-button">
									<button type="button" class="ai-buddy-button dependent-fields button-post-section" disabled><?php echo esc_html__( 'Generate sections', 'aibuddy-openai-chatgpt' ); ?></button>
								</div>
							</div>
						</div>
						<div class="running-generation" style="display: none;">
							<div class="running-generation-loader">
								<div class="running-generation-sub-title">
									<?php echo esc_html__( 'Generating post', 'aibuddy-openai-chatgpt' ); ?>...
									<span class="running-generation-count">00:00</span>
								</div>
								<div class="progress-bar-box"><span class="progress-bar" role="progressbar" style="width: 0;"></span></div>
							</div>
							<button class="ai-buddy-button outline button-abort"><?php echo esc_html__( 'Cancel', 'aibuddy-openai-chatgpt' ); ?></button>
						</div>
						<div class="section-field">
							<label class="section-subtitle">
								<span><?php echo esc_html__( 'Sections', 'aibuddy-openai-chatgpt' ); ?></span>
								<textarea placeholder="Please provide Sections for your post" id="post-section" class="dependent-fields" disabled></textarea>
							</label>
						</div>
						<div class="section-field-information">
							<span class="aibuddy-information"></span> <?php echo esc_html__( 'Feel free to modify the sections by adding, rewriting, removing, or reorganizing them as per your preference before clicking the "Generate Sections" button again. It is recommended to use the Markdown format.', 'aibuddy-openai-chatgpt' ); ?>
						</div>
					</div>
				</div>
				<div class="section" id="post-content-box">
					<div class="section-title"><?php echo esc_html__( 'Content', 'aibuddy-openai-chatgpt' ); ?></div>
					<div class="section-content">
						<div class="drop-down-box-wrapper">
							<div class="section-subtitle"><span><?php echo esc_html__( 'Number of paragraphs in each section', 'aibuddy-openai-chatgpt' ); ?></span></div>
							<div class="drop-down-box">
								<div class="drop-down-column">
									<select id="count-post-content" class="ai-buddy-select" title="count-post-content">
										<option value="1">1</option>
										<option value="2">2</option>
										<option value="3">3</option>
										<option value="4">4</option>
										<option value="6">6</option>
								</select>
								</div>
								<div class="drop-down-button">
									<button type="button" class="ai-buddy-button dependent-fields button-content-button" disabled><?php echo esc_html__( 'Generate content', 'aibuddy-openai-chatgpt' ); ?></button>
								</div>
							</div>
						</div>
						<div class="running-generation" style="display: none;">
							<div class="running-generation-loader">
								<div class="running-generation-sub-title">
									<?php echo esc_html__( 'Generating post', 'aibuddy-openai-chatgpt' ); ?>...
									<span class="running-generation-count">00:28</span>
								</div>
								<div class="progress-bar-box"><span class="progress-bar" role="progressbar" style="width: 0;"></span></div>
							</div>
							<button class="ai-buddy-button outline button-abort"><?php echo esc_html__( 'Cancel', 'aibuddy-openai-chatgpt' ); ?></button>
						</div>
						<div class="section-field">
							<label class="section-subtitle">
								<span><?php echo esc_html__( 'Content of the post', 'aibuddy-openai-chatgpt' ); ?></span>
								<textarea placeholder="Please input the content of the post" id="post-content" class="high-altitude dependent-fields" disabled></textarea>
							</label>
						</div>
						<div class="section-field-information">
							<span class="aibuddy-information"></span> <?php echo esc_html__( 'You can edit the content prior to clicking \'Create Post\'. Markdown is supported and will be converted to HTML once the post is generated.', 'aibuddy-openai-chatgpt' ); ?>
						</div>
					</div>
				</div>
				<div class="section" id="post-excerpt-box">
					<div class="section-title"><?php echo esc_html__( 'Excerpt', 'aibuddy-openai-chatgpt' ); ?></div>
					<div class="section-content">
						<div class="section-field">
							<textarea placeholder="Please provide the Excerpt of your post" id="post-excerpt" class="dependent-fields" disabled></textarea>
							<div class="drop-down-box-wrapper">
								<div class="section-button-box">
									<button type="button" class="ai-buddy-button dependent-fields button-excerpt-button" disabled><?php echo esc_html__( 'Generate Excerpt', 'aibuddy-openai-chatgpt' ); ?></button>
								</div>
							</div>
							<div class="running-generation" style="display: none;">
								<div class="running-generation-loader">
									<div class="running-generation-sub-title">
										<?php echo esc_html__( 'Generating post', 'aibuddy-openai-chatgpt' ); ?>...
										<span class="running-generation-count">00:00</span>
									</div>
									<div class="progress-bar-box"><span class="progress-bar" role="progressbar" style="width: 0%;"></span></div>
								</div>
								<button class="ai-buddy-button outline button-abort"><?php echo esc_html__( 'Cancel', 'aibuddy-openai-chatgpt' ); ?></button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php require AI_BUDDY_PATH . '/includes/admin/page-templates/content-builder/bulk-content-builder.php'; ?>
			<div class="ai-buddy-container-sidebar">
				<?php require AI_BUDDY_PATH . '/includes/admin/page-templates/content-builder/sidebar.php'; ?>
				<button type="button" class="ai-buddy-button button-full button-create-post" disabled><?php echo esc_html__( 'Create post', 'aibuddy-openai-chatgpt' ); ?></button>
			</div>
		</div>
	</div>
	<?php
	require AI_BUDDY_PATH . '/includes/admin/page-templates/content-builder/model-params.php';
	require AI_BUDDY_PATH . '/includes/admin/page-templates/content-builder/prompts.php';
	require AI_BUDDY_PATH . '/includes/admin/page-templates/content-builder/created-post.php';
	require AI_BUDDY_PATH . '/includes/admin/error-message.php';
	?>
</form>

