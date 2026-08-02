<?php
/**
 * Аналитика и верификация поисковиков из NeoBrain /public/config
 * или констант в wp-config.php.
 */
if (!defined('ABSPATH')) {
    exit;
}

function mb2_public_config_cache() {
    $cached = get_transient('mb2_public_config');
    if (is_array($cached)) {
        return $cached;
    }
    $url = home_url('/api/public/config');
    // на домене 5mb2 /api может проксироваться; иначе с NeoBrain
    $urls = array_unique([
        $url,
        'https://neobrain.site/api/public/config',
        'http://127.0.0.1:8502/public/config',
    ]);
    $cfg = [];
    foreach ($urls as $u) {
        $res = wp_remote_get($u, ['timeout' => 3, 'reject_unsafe_urls' => false]);
        if (is_wp_error($res)) {
            continue;
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = json_decode((string) wp_remote_retrieve_body($res), true);
        if ($code >= 200 && $code < 300 && is_array($body)) {
            $cfg = $body;
            break;
        }
    }
    // overrides from wp-config constants
    if (defined('MB2_METRIKA_ID') && MB2_METRIKA_ID) {
        $cfg['metrika_id'] = MB2_METRIKA_ID;
    }
    if (defined('MB2_GA4_ID') && MB2_GA4_ID) {
        $cfg['ga4_id'] = MB2_GA4_ID;
    }
    if (defined('MB2_GSC_VERIFICATION') && MB2_GSC_VERIFICATION) {
        $cfg['gsc_verification'] = MB2_GSC_VERIFICATION;
    }
    if (defined('MB2_YANDEX_VERIFICATION') && MB2_YANDEX_VERIFICATION) {
        $cfg['yandex_webmaster_verification'] = MB2_YANDEX_VERIFICATION;
    }
    set_transient('mb2_public_config', $cfg, 10 * MINUTE_IN_SECONDS);
    return $cfg;
}

add_action('wp_head', static function () {
    $c = mb2_public_config_cache();
    if (!empty($c['gsc_verification'])) {
        echo '<meta name="google-site-verification" content="' . esc_attr($c['gsc_verification']) . '" />' . "\n";
    }
    if (!empty($c['yandex_webmaster_verification'])) {
        echo '<meta name="yandex-verification" content="' . esc_attr($c['yandex_webmaster_verification']) . '" />' . "\n";
    }
    if (!empty($c['ga4_id'])) {
        $id = esc_js($c['ga4_id']);
        echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$id}\"></script>\n";
        echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$id}');</script>\n";
    }
    if (!empty($c['metrika_id'])) {
        $id = (int) $c['metrika_id'];
        echo "<script>(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,'script','https://mc.yandex.ru/metrika/tag.js','ym');ym({$id},'init',{clickmap:true,trackLinks:true,accurateTrackBounce:true});</script>\n";
        echo '<noscript><div><img src="https://mc.yandex.ru/watch/' . $id . '" style="position:absolute;left:-9999px" alt="" /></div></noscript>' . "\n";
    }
}, 2);
