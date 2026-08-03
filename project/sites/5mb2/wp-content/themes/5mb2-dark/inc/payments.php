<?php
/**
 * Оплата фиксированных пакетов 5MB2 через ЮKassa (API NeoBrain).
 * Один магазин / один webhook: https://neobrain.site/api/public/pay/webhook
 */
if (!defined('ABSPATH')) {
    exit;
}

function mb2_pay_api_base() {
    $base = apply_filters('mb2_pay_api_base', 'https://neobrain.site/api');
    return rtrim((string) $base, '/');
}

function mb2_package_for_service($slug) {
    $svc = function_exists('mb2_get_service') ? mb2_get_service($slug) : null;
    if (!$svc || empty($svc['pay_package'])) {
        return null;
    }
    return [
        'package'   => (string) $svc['pay_package'],
        'price_rub' => (int) ($svc['price_rub'] ?? 0),
        'title'     => (string) ($svc['title'] ?? $slug),
        'price'     => (string) ($svc['price'] ?? ''),
    ];
}

/** Блок оплаты картой на странице услуги. */
function mb2_render_pay_box($service_slug = '') {
    $pkg = mb2_package_for_service($service_slug);
    if (!$pkg || $pkg['price_rub'] <= 0) {
        return;
    }
    $amount = number_format($pkg['price_rub'], 0, '', ' ');
    ?>
    <div class="pay-box" data-mb2-pay
         data-package="<?php echo esc_attr($pkg['package']); ?>"
         data-amount="<?php echo esc_attr((string) $pkg['price_rub']); ?>">
      <h3 class="pay-box-title">Оплатить картой</h3>
      <p class="muted tiny">Фиксированная цена пакета: <strong><?php echo esc_html($amount); ?> ₽</strong>. После оплаты напишем по email.</p>
      <label class="pay-box-label">Email для чека и связи
        <input type="email" name="pay_email" autocomplete="email" required placeholder="you@company.ru" />
      </label>
      <p class="pay-box-note muted tiny" data-pay-note hidden></p>
      <button type="button" class="btn btn-primary btn-lg" data-pay-submit>
        Оплатить <?php echo esc_html($amount); ?> ₽
      </button>
      <p class="muted tiny" style="margin:10px 0 0">
        Или <a class="text-link" href="<?php echo esc_url(home_url('/rekvizity/')); ?>">реквизиты для перевода</a>
        · заявка ниже — если нужен индивидуальный объём.
      </p>
    </div>
    <?php
}
