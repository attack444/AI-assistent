</main>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <p class="logo-text">5MB2<span>Digital</span></p>
      <p>SEO-продвижение сайтов по России. Трафик, видимость, заявки — с прозрачной отчётностью.</p>
    </div>
    <div>
      <p class="footer-title">Навигация</p>
      <?php mb2_footer_nav(); ?>
    </div>
    <div>
      <p class="footer-title">Услуги</p>
      <?php foreach (array_slice(mb2_services_catalog(), 0, 4, true) as $slug => $svc) : ?>
        <a href="<?php echo esc_url(mb2_service_url($slug)); ?>"><?php echo esc_html($svc['title']); ?></a>
      <?php endforeach; ?>
      <a href="<?php echo esc_url(home_url('/services/')); ?>">Все услуги</a>
    </div>
    <div>
      <p class="footer-title">Контакт</p>
      <a href="mailto:hello@5mb2.ru">hello@5mb2.ru</a>
      <a href="https://vk.com/5mb2online" target="_blank" rel="noopener">VK · 5mb2online</a>
      <a href="<?php echo esc_url(home_url('/#contact')); ?>">Оставить заявку</a>
      <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">Конфиденциальность</a>
    </div>
  </div>
  <div class="wrap footer-bottom">
    <span>© <?php echo esc_html(gmdate('Y')); ?> <?php echo esc_html(mb2_legal('brand') ?: '5MB2 Digital'); ?></span>
    <span>
      <?php if (mb2_legal('npd') === '1') : ?>Самозанятый (НПД) · <?php endif; ?>
      <a class="text-link" href="<?php echo esc_url(home_url('/oferta/')); ?>">Оферта</a>
      ·
      <a class="text-link" href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">152-ФЗ</a>
    </span>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
