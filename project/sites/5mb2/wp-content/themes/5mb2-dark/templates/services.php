<?php
/**
 * Template Name: Каталог услуг
 */
get_header();
$services = mb2_services_catalog();
?>
<section class="page-shell">
  <div class="wrap">
    <header class="section-head reveal">
      <h1>Услуги SEO</h1>
      <p>Выберите задачу — откроется страница с описанием, сроками и формой заказа.</p>
    </header>
    <div class="service-cards">
      <?php foreach ($services as $slug => $svc) : ?>
        <a class="service-card reveal" href="<?php echo esc_url(mb2_service_url($slug)); ?>">
          <div class="service-card-media">
            <img src="<?php echo esc_url($svc['image']); ?>" alt="" width="640" height="400" loading="lazy" />
          </div>
          <div class="service-card-body">
            <h2><?php echo esc_html($svc['title']); ?></h2>
            <p><?php echo esc_html($svc['short']); ?></p>
            <span class="meta-pill"><?php echo esc_html($svc['price']); ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php get_footer(); ?>
