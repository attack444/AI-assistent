<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
global $post;
$general_setting = get_option( 'ai_buddy', array() );
?>
<div class="ai-buddy-meta-box-title"><?php echo esc_html__( 'Generate the WooCommerce fields.', 'aibuddy-openai-chatgpt' ); ?></div>
<?php
if ( isset( $general_setting['modules']['woocommerce'] ) && $general_setting['modules']['woocommerce'] ) :
	?>
	<button type="submit" class="ai-buddy-button button-full button-product-popup"><?php echo esc_html__( 'Generate', 'aibuddy-openai-chatgpt' ); ?></button>
	<?php
endif;
if ( isset( $general_setting['modules']['images'] ) && $general_setting['modules']['images'] ) :
	?>
	<button type="submit" data-post-id="<?php echo esc_attr( $post->ID ); ?>" class="ai-buddy-button button-full button-post-image-generate"><?php echo esc_html__( 'Generate Image', 'aibuddy-openai-chatgpt' ); ?></button>
	<?php
endif;
?>

<div class="ai-buddy-modal-window product-generate-popup">
	<div class="ai-buddy-modal-window-wrapper">
		<div class="modal-header">
			<div class="section-title product-title-fields"><?php echo esc_html__( 'WooCommerce Product Generator', 'aibuddy-openai-chatgpt' ); ?></div>
			<div class="section-title product-title-image"><?php echo esc_html__( 'WooCommerce Product Image', 'aibuddy-openai-chatgpt' ); ?></div>
			<div class="modal-close"><span class="aibuddy-close"></span></div>
		</div>
		<div class="modal-body">
			<div class="ai-buddy-container">
				<div class="section product-image">
					<div class="section-content generated-post-headers"></div>
				</div>
				<div class="section product-fields">
					<div class="section-content">
						<div class="section-field">
							<label class="section-subtitle">
								<span><?php echo esc_html__( 'Define your product', 'aibuddy-openai-chatgpt' ); ?></span>
								<var class="with-line">
									<input type="text" id="product-new-content-generator" />
									<button class="ai-buddy-button right-alignment button-product-generator" disabled><span class="aibuddy-loader button-loader"></span><?php echo esc_html__( 'Generate', 'aibuddy-openai-chatgpt' ); ?></button>
								</var>
							</label>
						</div>
					</div>
				</div>
				<div class="section product-fields">
					<div class="section-content">
						<div class="section-field">
							<label class="section-subtitle">
								<span><?php echo esc_html__( 'Title', 'aibuddy-openai-chatgpt' ); ?></span>
								<var>
									<input type="text" id="new-product-title" disabled />
									<button class="ai-buddy-button right-alignment outline button-product-title" data-done="<?php echo esc_html__( 'Done', 'aibuddy-openai-chatgpt' ); ?>" disabled><?php echo esc_html__( 'Apply', 'aibuddy-openai-chatgpt' ); ?></button>
								</var>
							</label>
						</div>
					</div>
				</div>
				<div class="section product-fields">
					<div class="section-content">
						<div class="section-field">
							<label class="section-subtitle">
								<span><?php echo esc_html__( 'Description', 'aibuddy-openai-chatgpt' ); ?></span>
								<var>
									<textarea id="new-product-description" disabled></textarea>
									<button class="ai-buddy-button right-alignment outline button-product-description" data-done="<?php echo esc_html__( 'Done', 'aibuddy-openai-chatgpt' ); ?>" disabled><?php echo esc_html__( 'Apply', 'aibuddy-openai-chatgpt' ); ?></button>
								</var>
							</label>
						</div>
					</div>
				</div>
				<div class="section product-fields">
					<div class="section-content">
						<div class="section-field">
							<label class="section-subtitle">
								<span><?php echo esc_html__( 'Short Description', 'aibuddy-openai-chatgpt' ); ?></span>
								<var>
									<textarea id="new-product-excerpt" disabled></textarea>
									<button class="ai-buddy-button right-alignment outline button-product-excerpt" data-done="<?php echo esc_html__( 'Done', 'aibuddy-openai-chatgpt' ); ?>" disabled><?php echo esc_html__( 'Apply', 'aibuddy-openai-chatgpt' ); ?></button>
								</var>
							</label>
						</div>
					</div>
				</div>
				<div class="section product-fields">
					<div class="section-content">
						<div class="section-field">
							<label class="section-subtitle">
								<span><?php echo esc_html__( 'Product tags', 'aibuddy-openai-chatgpt' ); ?></span>
								<var>
									<input type="text" id="new-product-tags" disabled />
									<button class="ai-buddy-button right-alignment outline button-product-tags" data-done="<?php echo esc_html__( 'Done', 'aibuddy-openai-chatgpt' ); ?>" disabled><?php echo esc_html__( 'Apply', 'aibuddy-openai-chatgpt' ); ?></button>
								</var>
							</label>
						</div>
					</div>
				</div>
				<div class="section product-fields">
					<div class="section-content">
						<div class="section-button-box section-button-box-full">
							<button type="button" class="ai-buddy-button gray button-abort-product-generate"><?php echo esc_html__( 'Cancel', 'aibuddy-openai-chatgpt' ); ?></button>
							<button class="ai-buddy-button right-alignment button-product-generate" data-done="<?php echo esc_html__( 'Done', 'aibuddy-openai-chatgpt' ); ?>" disabled><?php echo esc_html__( 'Write all fields', 'aibuddy-openai-chatgpt' ); ?></button>
						</div>
					</div>
				</div>
				<div class="section product-error">
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
