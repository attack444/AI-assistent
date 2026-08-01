<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//phcpcs:ignore because no nonce needed since it can be linked to directly
$current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : ''; //phpcs:ignore
$general_setting = get_option( 'ai_buddy', array() );
$has_any_api = \AiBuddy\ApiManager::hasAnyApiKey();
$openai_required_tabs = ['ai_buddy_image_generator', 'ai_buddy_chatbot'];
$is_openai_tab = in_array($current_page, $openai_required_tabs);
if ($is_openai_tab) {
    $has_openai = \AiBuddy\ApiManager::hasProviderApiKey(\AiBuddy\ApiManager::PROVIDER_OPENAI);
    $validator = $has_openai ? 'valid' : 'invalid';
} else {
    $validator = $has_any_api ? 'valid' : 'invalid';
}

if (!$is_openai_tab && $has_any_api) {
    return;
}
?>
<div class="api-key-settings <?php echo esc_attr( $validator ); ?>">
	<div class="api-key-settings-icon">
		<img src="<?php echo esc_url( AI_BUDDY_FILES_PATH . 'assets/images/alert.svg' ); ?>" width="18" height="18" alt="<?php echo esc_attr__('AiBud WP Plugin get API Key', 'aibuddy-openai-chatgpt'); ?>" />
	</div>
	<div class="api-key-settings-content">
		<?php if ($is_openai_tab): ?>
		<span><?php echo esc_html__( 'Please enter your OpenAI API key!', 'aibuddy-openai-chatgpt' ); ?></span>
		<span class="api-key-validation">
			<?php
			printf(
			// Translators: %1$s: Open Link for account api key, %2$s: Close Link for account api key
				esc_html__( 'You can get your API Keys in your %1$sOpenAI Account%2$s.', 'aibuddy-openai-chatgpt' ),
				'<a href="https://platform.openai.com/account/usage/" target="_blank" rel="nofollow">',
				'</a>'
			);
			?>
		</span>
		<?php else: ?>
		<span><?php echo esc_html__( 'Please enter at least one API key!', 'aibuddy-openai-chatgpt' ); ?></span>
		<span class="api-key-validation">
			<?php echo esc_html__( 'You need to configure at least one API key in the settings.', 'aibuddy-openai-chatgpt' ); ?>
		</span>
		<?php endif; ?>
	</div>
	<div class="api-key-settings-button">
		<a href="<?php echo esc_url(admin_url('admin.php?page=ai_buddy_settings')); ?>" class="ai-buddy-button">
			<?php echo esc_html__('Go to settings', 'aibuddy-openai-chatgpt'); ?>
		</a>
	</div>
</div>
