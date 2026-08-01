<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="aipkit_cw_inspector_card aipkit_cw_inspector_card--advanced">
    <div class="aipkit_cw_inspector_card_header">
        <div class="aipkit_cw_inspector_card_header_copy">
            <div class="aipkit_cw_inspector_card_title">
                <?php esc_html_e('Advanced', 'gpt3-ai-content-generator'); ?>
            </div>
        </div>
    </div>
    <div class="aipkit_cw_inspector_card_body aipkit_cw_inspector_card_body--advanced">
        <section class="aipkit_cw_advanced_section">
            <?php include __DIR__ . '/form-inputs/vector-settings.php'; ?>
        </section>
    </div>
</section>
