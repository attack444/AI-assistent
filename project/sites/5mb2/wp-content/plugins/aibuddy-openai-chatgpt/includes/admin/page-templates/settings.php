<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$general_setting          = get_option( 'ai_buddy', array() );
$usage_setting            = get_option( 'ai_buddy_openai_usage', array() );
$usage_date               = array_key_first( $usage_setting );
$total_price              = isset( $usage_setting[ $usage_date ]['text-davinci-003']['total_tokens'] ) / 1000 * 0.02;

if (isset($general_setting['modules']['titles'])) {
    $post_title_suggestions = $general_setting['modules']['titles'] ? 'checked' : '';
} else {
    $post_title_suggestions = 'checked';
}

if (isset($general_setting['modules']['excerpts'])) {
    $post_excerpt_suggestions = $general_setting['modules']['excerpts'] ? 'checked' : '';
} else {
    $post_excerpt_suggestions = 'checked';
}

if (isset($general_setting['modules']['images'])) {
    $post_image_suggestions = $general_setting['modules']['images'] ? 'checked' : '';
} else {
    $post_image_suggestions = 'checked';
}

if (isset($general_setting['modules']['woocommerce'])) {
    $post_product_generator = $general_setting['modules']['woocommerce'] ? 'checked' : '';
} else {
    $post_product_generator = 'checked';
}

?>
<form id="plugin-settings">
	<div class="ai-buddy-container">
		<div class="ai-buddy-container-row">
			<div class="ai-buddy-container-content">
                <div class="section ai-buddy-accordion-section">
                    <div class="section-title active"><?php echo esc_html__( 'API Key Settings', 'aibuddy-openai-chatgpt' ); ?></div>
                    <div class="section-content content-params">
                        <div class="section-field">
                            <div class="section-subtitle"><span><?php echo esc_html__( 'Open AI API Key', 'aibuddy-openai-chatgpt' ); ?></span></div>
                            <input type="password" id="ai_buddy-api-key" value="<?php echo esc_attr( $general_setting['openai']['apikey'] ?? '' ); ?>" placeholder="<?php echo esc_attr__('Enter API Key', 'aibuddy-openai-chatgpt'); ?>" />
                        </div>
                        <div class="section-field-information">
                            <span class="aibuddy-information"></span>
                            <?php
                            printf(
                                // Translators: %1$s: Open Link for account api key, %2$s: Close Link for account api key
                                esc_html__( 'You can get your API Keys in your %1$sOpenAI Account%2$s.', 'aibuddy-openai-chatgpt' ),
                                '<a href="https://platform.openai.com/account/usage/" target="_blank" rel="nofollow">',
                                '</a>'
                            );
                            ?>
                        </div>

                        <div class="section-field">
                            <div class="section-subtitle"><span><?php echo esc_html__( 'Google AI API Key', 'aibuddy-openai-chatgpt' ); ?></span></div>
                            <input type="password" id="ai_buddy-google-api-key" value="<?php echo isset($general_setting['googleai']['apikey']) ? esc_attr( $general_setting['googleai']['apikey'] ) : ''; ?>" placeholder="Enter API Key" />
                        </div>
                        <div class="section-field-information">
                            <span class="aibuddy-information"></span>
                            <?php
                            printf(
                                // Translators: %1$s: Open Link for account api key, %2$s: Close Link for account api key
                                esc_html__( 'You can get your API Keys in your %1$sGoogle AI Studio%2$s.', 'aibuddy-openai-chatgpt' ),
                                '<a href="https://makersuite.google.com/app/apikey?authuser=1" target="_blank" rel="nofollow">',
                                '</a>'
                            );
                            ?>
                        </div>

                        <div class="section-field">
                            <div class="section-subtitle"><span><?php echo esc_html__( 'Claude AI API Key', 'aibuddy-openai-chatgpt' ); ?></span></div>
                            <input type="password" id="ai_buddy-claude-api-key" value="<?php echo isset($general_setting['claude']['apikey']) ? esc_attr( $general_setting['claude']['apikey'] ) : ''; ?>" placeholder="Enter API Key" />
                        </div>
                        <div class="section-field-information">
                            <span class="aibuddy-information"></span>
                            <?php
                            printf(
                                // Translators: %1$s: Open Link for account api key, %2$s: Close Link for account api key
                                esc_html__( 'You can get your API Keys in your %1$sAnthropic Console%2$s.', 'aibuddy-openai-chatgpt' ),
                                '<a href="https://console.anthropic.com/settings/keys" target="_blank" rel="nofollow">',
                                '</a>'
                            );
                            ?>
                        </div>

                        <div class="section-field">
                            <div class="section-subtitle"><span><?php echo esc_html__( 'OpenRouter API Key', 'aibuddy-openai-chatgpt' ); ?></span></div>
                            <input type="password" id="ai_buddy-open-router-api-key" value="<?php echo isset($general_setting['openrouter']['apikey']) ? esc_attr( $general_setting['openrouter']['apikey'] ) : ''; ?>" placeholder="Enter API Key" />
                        </div>
                        <div class="section-field-information">
                            <span class="aibuddy-information"></span>
                            <?php
                            printf(
                                // Translators: %1$s: Open Link for account api key, %2$s: Close Link for account api key
                                esc_html__( 'You can get your API Keys in your %1$sOpenRouter%2$s.', 'aibuddy-openai-chatgpt' ),
                                '<a href="https://openrouter.ai/keys" target="_blank" rel="nofollow">',
                                '</a>'
                            );
                            ?>
                        </div>
                    </div>
                </div>
                                
                <div class="section ai-buddy-accordion-section">
                    <div class="section-title"><?php echo esc_html__( 'Block Editor Settings', 'aibuddy-openai-chatgpt' ); ?></div>
                    <div class="section-content content-params">
                        <div class="section-field">
                            <div class="section-subtitle"><span><?php echo esc_html__( 'Default AI Model:', 'aibuddy-openai-chatgpt' ); ?></span></div>
                        </div>
                        <select name="ai_buddy-fse-default-model" id="ai_buddy-fse-default-model" class="ai-buddy-select">
                            <?php require AI_BUDDY_PATH . '/includes/admin/model-list.php'; ?>
                        </select>
                    </div>
                </div>

                <div class="section ai-buddy-accordion-section">
                    <div class="section-title"><?php echo esc_html__( 'Assistants', 'aibuddy-openai-chatgpt' ); ?></div>
                    <div class="section-content content-params">
                        <div class="section-field">
                            <div class="check-box">
                                <input type="checkbox" class="checkbox" 
                                       value="<?php echo esc_attr($general_setting['modules']['titles'] ?? ''); ?>" 
                                       id="post-title-suggestions" 
                                       <?php echo esc_attr($post_title_suggestions ? 'checked' : ''); ?> />
                                <label for="post-title-suggestions"></label>
                                <span class="checkbox-text"><?php echo esc_html__('Title Suggestions', 'aibuddy-openai-chatgpt'); ?></span>
                                <div class="section-field-information">
                                    <span class="aibuddy-information"></span> <?php echo esc_html__( 'Provide several headings that are relevant to your content.', 'aibuddy-openai-chatgpt' ); ?>
                                </div>
                            </div>
                        </div>
                        <div class="section-field">
                            <div class="check-box">
                                <input type="checkbox" class="checkbox" value="<?php echo esc_attr( $general_setting['modules']['excerpts'] ); ?>" id="post-excerpt-suggestions" <?php echo esc_attr( $post_excerpt_suggestions ); ?> />
                                <label for="post-excerpt-suggestions"></label>
                                <span class="checkbox-text"><?php echo esc_html__( 'Excerpt Suggestions', 'aibuddy-openai-chatgpt' ); ?></span>
                                <div class="section-field-information">
                                    <span class="aibuddy-information"></span> <?php echo esc_html__( 'Propose some excerpts based on your content.', 'aibuddy-openai-chatgpt' ); ?>
                                </div>
                            </div>
                        </div>
                        <div class="section-field">
                            <div class="check-box">
                                <input type="checkbox" class="checkbox" value="
								<?php
                                if ( isset( $general_setting['modules']['images'] ) ) {
                                    echo esc_attr( $general_setting['modules']['images'] );
                                }
                                ?>
							" id="post-image-suggestions" <?php echo esc_attr( $post_image_suggestions ); ?> />
                                <label for="post-image-suggestions"></label>
                                <span class="checkbox-text"><?php echo esc_html__( 'Image Suggestions', 'aibuddy-openai-chatgpt' ); ?></span>
                                <div class="section-field-information">
                                    <span class="aibuddy-information"></span> <?php echo esc_html__( 'Suggest some images based on your content.', 'aibuddy-openai-chatgpt' ); ?>
                                </div>
                            </div>
                        </div>
                        <div class="section-field">
                            <div class="check-box">
                                <input type="checkbox" class="checkbox" value="<?php echo esc_attr( $general_setting['modules']['woocommerce'] ); ?>" id="post-product-generator" <?php echo esc_attr( $post_product_generator ); ?> />
                                <label for="post-product-generator"></label>
                                <span class="checkbox-text"><?php echo esc_html__( 'WooCommerce Product Generator', 'aibuddy-openai-chatgpt' ); ?></span>
                                <div class="section-field-information">
                                    <span class="aibuddy-information"></span> <?php echo esc_html__( 'Populate all WooCommerce fields for a specific product.', 'aibuddy-openai-chatgpt' ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-buttons-box">
                    <button type="submit" class="ai-buddy-button update-plugin-setting"><?php echo esc_html__( 'Save', 'aibuddy-openai-chatgpt' ); ?></button>
                </div>
			</div>
			<div class="ai-buddy-container-sidebar">
				<div class="section">
					<div class="section-title"><span><?php echo esc_html__( 'Usage', 'aibuddy-openai-chatgpt' ); ?></span></div>
					<div class="usage-section">
					<?php if ( is_array( $usage_setting ) && ! empty( $usage_setting ) ) : ?>
						<div class="usage-section-data"><?php echo esc_attr( $usage_date ); ?> <span class="total-requests-price"></span></div>
						<div class="usage-section-content">
						<?php
							$usage_tokens = $usage_setting[ $usage_date ];
						foreach ( $usage_tokens as $token => $token_value ) :
							?>
							<div class="tokens-data">
								<div class="tokens-data-key">
									<?php echo esc_attr( $token ); ?>:
								</div>
								<div class="tokens-data-tokens">
									<?php if ( isset( $token_value['total'] ) ) : ?>
										<input type="hidden" id="total-images-data" value="<?php echo esc_attr( $token_value['total'] ); ?>" />
										<?php
									endif;
									if ( isset( $token_value['total_tokens'] ) ) :
										?>
									<input type="hidden" id="total-tokens-data" value="<?php echo esc_attr( $token_value['total_tokens'] ); ?>" />
										<?php
									endif;
									echo  esc_attr( isset( $token_value['total'] ) ? $token_value['total'] . ' images (0.02$)' : '' );
									echo esc_attr( isset( $token_value['total_tokens'] ) ?  $token_value['total_tokens'] . ' tokens (0.02$)' : '');
									?>
								</div>
							</div>
							<?php
						endforeach;
						?>
						</div>
					<?php else : ?>
						<?php echo esc_html__( 'You have not generated any content yet.', 'aibuddy-openai-chatgpt' ); ?>
					<?php endif; ?>
					</div>
					<div class="section-field-information">
						<span class="aibuddy-information"></span>
						<?php
						printf(
						    // Translators: %1$s: Open Link for account api key, %2$s: Close Link for account api key
							esc_html__( 'For exact amounts, please refer to your %1$sOpenAI account%2$s. If you wish to have more control over the usage of AI, including adding conditions or setting limits, you may want to consider AiBud WP Pro.', 'aibuddy-openai-chatgpt' ),
							'<a href="https://platform.openai.com/account/usage/" target="_blank" rel="nofollow">',
							'</a>'
						);
						?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="plugin-settings-request">
		<span class="successful-request"><span class="settings-request-icon aibuddy-solid-check"></span> <?php echo esc_html__( 'Changes successfully saved', 'aibuddy-openai-chatgpt' ); ?></span>
		<span class="error-request"><span class="settings-request-icon aibuddy-solid-cancel"></span> <?php echo esc_html__( 'Something went wrong', 'aibuddy-openai-chatgpt' ); ?></span>
	</div>
    <?php
    require AI_BUDDY_PATH . '/includes/admin/error-message.php';
    ?>
</form>
