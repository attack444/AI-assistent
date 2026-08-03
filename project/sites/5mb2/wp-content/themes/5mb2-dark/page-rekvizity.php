<?php
/**
 * Template for page slug: rekvizity
 */
get_header();

$b = mb2_bank_details();
$rows = [
    ['Получатель', $b['payee']],
    ['ИНН получателя', $b['payee_inn'] ?: '—'],
    ['Номер счёта', $b['account_fmt'] ?: $b['account']],
    ['Тип счёта', $b['account_type']],
    ['Банк', $b['bank_name']],
    ['БИК', $b['bik']],
    ['Корр. счёт', $b['corr_fmt'] ?: $b['corr']],
    ['ИНН банка', $b['bank_inn']],
    ['КПП банка', $b['bank_kpp']],
    ['Код подразделения', $b['branch']],
    ['Адрес подразделения', $b['address']],
];
?>
<section class="page-shell">
  <div class="wrap">
    <?php if (function_exists('mb2_render_breadcrumbs')) { mb2_render_breadcrumbs(); } ?>

    <div class="rekvizity-layout reveal is-in" style="margin-top:12px">
      <div class="prose">
        <h1>Реквизиты</h1>
        <p>
          Платёжные реквизиты для оплаты услуг
          <strong><?php echo esc_html($b['brand']); ?></strong>.
          <?php if ($b['npd']) : ?>Самозанятый (НПД), без НДС.<?php endif; ?>
        </p>

        <dl class="rekvizity-list">
          <?php foreach ($rows as [$label, $value]) :
              if ($value === '' || $value === null) {
                  continue;
              }
              $copy = preg_replace('/\s+/', '', (string) $value);
              $is_num = (bool) preg_match('/^\d[\d\s]*$/', (string) $value);
              ?>
            <div class="rekvizity-row">
              <dt><?php echo esc_html($label); ?></dt>
              <dd>
                <span class="rekvizity-value"<?php echo $is_num ? ' data-copy="' . esc_attr($copy) . '"' : ''; ?>>
                  <?php echo esc_html($value); ?>
                </span>
                <?php if ($is_num) : ?>
                  <button type="button" class="rekvizity-copy" data-copy="<?php echo esc_attr($copy); ?>">Копировать</button>
                <?php endif; ?>
              </dd>
            </div>
          <?php endforeach; ?>
        </dl>

        <p>
          Email:
          <a href="mailto:<?php echo esc_attr($b['email']); ?>"><?php echo esc_html($b['email']); ?></a>
          ·
          <a href="<?php echo esc_url(home_url('/contacts/')); ?>">Контакты</a>
          ·
          <a href="<?php echo esc_url(home_url('/oferta/')); ?>">Оферта</a>
        </p>
        <p class="muted">В назначении платежа укажите услугу и адрес сайта. Чек НПД выдаётся через «Мой налог».</p>
        <p class="muted">AI-платформа: <a href="https://neobrain.site/rekvizity/">NeoBrain → реквизиты</a></p>
      </div>
    </div>
  </div>
</section>
<script>
(function () {
  document.querySelectorAll('.rekvizity-copy').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var v = btn.getAttribute('data-copy') || '';
      if (!v || !navigator.clipboard) return;
      navigator.clipboard.writeText(v).then(function () {
        var t = btn.textContent;
        btn.textContent = 'Скопировано';
        setTimeout(function () { btn.textContent = t; }, 1200);
      });
    });
  });
})();
</script>
<?php get_footer(); ?>
