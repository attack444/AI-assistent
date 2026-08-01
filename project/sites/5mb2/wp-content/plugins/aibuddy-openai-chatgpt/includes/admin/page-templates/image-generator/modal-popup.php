<?php
    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="ai-buddy-modal-window image-generator-popup">
	<div class="ai-buddy-modal-wrapper">
		<div class="modal-header">
			<div class="section-title"><?php echo esc_html__( 'Image Details', 'aibuddy-openai-chatgpt' ); ?></div>
			<div class="modal-close"><span class="aibuddy-close"></span></div>
		</div>
		<div class="modal-body">
			<div class="image-modal-row">
				<div class="images-group">
					<img src="" class="popup-image">
					<div class="edit-media-wrapper">
						<div class="edit-media"><i class="aibuddy-check-outline"></i><span class="attachment-id"></span><?php echo esc_html__( 'media  has been created!', 'aibuddy-openai-chatgpt' ); ?></div>
						<a href="#" class="edit-media-button" target="_blank"><?php echo esc_html__( 'Edit Media', 'aibuddy-openai-chatgpt' ); ?></a>
					</div>
				</div>
				<div class="image-info">
					<form id="image-download-form">
						<div class="image-info-subtitle"><?php echo esc_html__( 'Title', 'aibuddy-openai-chatgpt' ); ?></div>
						<textarea name="image-title"></textarea>
						<div class="image-info-subtitle"><?php echo esc_html__( 'Caption', 'aibuddy-openai-chatgpt' ); ?></div>
						<textarea name="image-caption"></textarea>
						<div class="image-info-subtitle"><?php echo esc_html__( 'Description', 'aibuddy-openai-chatgpt' ); ?></div>
						<textarea name="image-description"></textarea>
						<div class="image-info-subtitle"><?php echo esc_html__( 'Alternative text', 'aibuddy-openai-chatgpt' ); ?></div>
						<textarea name="image-alt"></textarea>
						<div class="image-info-subtitle"><?php echo esc_html__( 'File name', 'aibuddy-openai-chatgpt' ); ?></div>
						<input type="text" name="image-file-name">
						<div class="popup-buttons-wrapper">
							<button class="fill-with-ai-button popup-button"><i class="aibuddy-loader"></i><?php echo esc_html__( 'Fill with AI', 'aibuddy-openai-chatgpt' ); ?></button>
							<button class="add-to-media popup-button"><i class="aibuddy-loader"></i><?php echo esc_html__( 'Add to media library', 'aibuddy-openai-chatgpt' ); ?></button>
							<a href="#" class="download popup-button" target="_blank"><?php echo esc_html__( 'Download', 'aibuddy-openai-chatgpt' ); ?></a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
	<div class="ai-buddy-modal-window-overlay"></div>
</div>
