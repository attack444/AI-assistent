<?php
/**
 * PufferWorks product recommendations shown at the end of Other settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

$aipkit_pufferworks_products = [
    [
        'name'        => 'PufferSights',
        'description' => __('AI crawler traffic and llms.txt coverage.', 'gpt3-ai-content-generator'),
        'icon'        => 'puffersights.svg',
        'url'         => 'https://wordpress.org/plugins/puffersights-ai-crawler-insights/',
    ],
    [
        'name'        => 'PufferDesk',
        'description' => __('A desktop-like WordPress admin.', 'gpt3-ai-content-generator'),
        'icon'        => 'pufferdesk.svg',
        'url'         => 'https://wordpress.org/plugins/pufferdesk/',
    ],
    [
        'name'        => 'Pufferbay',
        'description' => __('Roadmaps, feedback, and changelogs.', 'gpt3-ai-content-generator'),
        'icon'        => 'pufferbay.svg',
        'url'         => 'https://wordpress.org/plugins/pufferbay/',
    ],
];
?>

<section class="aipkit_settings_product_promotions" aria-labelledby="aipkit_settings_product_promotions_title">
    <h3 class="aipkit_settings_product_promotions_title" id="aipkit_settings_product_promotions_title">
        <?php esc_html_e('More from PufferWorks', 'gpt3-ai-content-generator'); ?>
    </h3>

    <div class="aipkit_settings_product_list">
        <?php foreach ($aipkit_pufferworks_products as $aipkit_product) : ?>
            <article class="aipkit_settings_product">
                <img
                    class="aipkit_settings_product_logo"
                    src="<?php echo esc_url(WPAICG_PLUGIN_URL . 'admin/images/plugins/' . $aipkit_product['icon']); ?>"
                    alt=""
                    width="32"
                    height="32"
                />
                <p class="aipkit_settings_product_summary">
                    <strong class="aipkit_settings_product_name"><?php echo esc_html($aipkit_product['name']); ?></strong>
                    <span class="aipkit_settings_product_description">&mdash; <?php echo esc_html($aipkit_product['description']); ?></span>
                </p>
                <a
                    class="aipkit_settings_product_link"
                    href="<?php echo esc_url($aipkit_product['url']); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="<?php echo esc_attr(sprintf(
                        /* translators: %s: product name. */
                        __('Learn more about %s (opens in a new tab)', 'gpt3-ai-content-generator'),
                        $aipkit_product['name']
                    )); ?>"
                >
                    <span class="aipkit_settings_product_link_label"><?php esc_html_e('Learn more', 'gpt3-ai-content-generator'); ?></span>
                    <span class="aipkit_settings_product_link_arrow" aria-hidden="true">↗</span>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
