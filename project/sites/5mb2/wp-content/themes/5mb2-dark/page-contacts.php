<?php
/**
 * Template for page slug: contacts
 * Динамические реквизиты + форма заявки.
 */
get_header();

$name  = mb2_legal_display_name();
$email = mb2_legal('email') ?: 'hello@5mb2.ru';
$phone = mb2_legal('phone');
$inn   = mb2_legal('inn');
$city  = mb2_legal('city');
$brand = mb2_legal('brand') ?: '5MB2 Digital';
?>
<section class="page-shell">
  <div class="wrap">
    <?php if (function_exists('mb2_render_breadcrumbs')) { mb2_render_breadcrumbs(); } ?>

    <div class="contacts-layout reveal is-in" style="margin-top:12px">
      <div class="prose contacts-info">
        <h1>Контакты</h1>
        <p><strong><?php echo esc_html($brand); ?></strong> — SEO-продвижение сайтов и digital-проекты.</p>
        <p>
          Исполнитель: <?php echo esc_html($name); ?><br>
          Статус: самозанятый (НПД), без НДС<br>
          Регион: <?php echo esc_html($city ?: 'Россия'); ?>
        </p>
        <?php if ($inn) : ?>
          <p>ИНН: <?php echo esc_html($inn); ?></p>
        <?php endif; ?>
        <p>Email: <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></p>
        <?php if ($phone) : ?>
          <p>Телефон: <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></p>
        <?php endif; ?>
        <p>VK: <a href="https://vk.com/5mb2online" target="_blank" rel="noopener">vk.com/5mb2online</a></p>
        <p>
          <a href="<?php echo esc_url(home_url('/rekvizity/')); ?>">Реквизиты для оплаты</a>
          ·
          <a href="<?php echo esc_url(home_url('/oferta/')); ?>">Публичная оферта</a>
          ·
          <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">Конфиденциальность</a>
        </p>
        <p class="muted">Платформа AI: <a href="https://neobrain.site/contacts/">NeoBrain → контакты</a> · <a href="https://neobrain.site/rekvizity/">реквизиты</a></p>
      </div>

      <div class="contacts-form-panel">
        <h2 class="contacts-form-title">Оставить заявку</h2>
        <p class="muted" style="margin:0 0 14px">Ответим на почту. Обычно в течение рабочего дня.</p>
        <?php mb2_render_lead_form(); ?>
      </div>
    </div>
  </div>
</section>
<?php get_footer(); ?>
