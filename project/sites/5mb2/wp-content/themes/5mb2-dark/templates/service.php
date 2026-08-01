<?php
/**
 * Template Name: Услуга SEO
 */
get_header();

$slug = get_post_meta(get_the_ID(), '_mb2_service_slug', true);
if (!$slug) {
    $slug = get_post_field('post_name', get_the_ID());
}
$svc = mb2_get_service($slug);
if (!$svc) {
    $svc = [
        'title'   => get_the_title(),
        'short'   => '',
        'price'   => '',
        'term'    => '',
        'image'   => get_template_directory_uri() . '/assets/img/service-growth.jpg',
        'bullets' => [],
        'body'    => '',
    ];
}
?>
<section class="page-shell service-page">
  <div class="wrap">
    <p class="crumb muted tiny reveal is-in">
      <a class="text-link" href="<?php echo esc_url(home_url('/')); ?>">Главная</a>
      ·
      <a class="text-link" href="<?php echo esc_url(home_url('/services/')); ?>">Услуги</a>
      · <?php echo esc_html($svc['title']); ?>
    </p>

    <div class="service-layout">
      <div>
        <header class="section-head reveal">
          <h1><?php echo esc_html($svc['title']); ?></h1>
          <p><?php echo esc_html($svc['short']); ?></p>
        </header>
        <?php if (!empty($svc['image'])) : ?>
          <figure class="media-frame reveal">
            <img src="<?php echo esc_url($svc['image']); ?>" alt="<?php echo esc_attr($svc['title']); ?>" width="1000" height="700" loading="lazy" />
          </figure>
        <?php endif; ?>
        <div class="prose reveal">
          <?php echo wp_kses_post($svc['body'] ?: apply_filters('the_content', get_the_content())); ?>
          <?php if (!empty($svc['bullets'])) : ?>
            <ul>
              <?php foreach ($svc['bullets'] as $b) : ?>
                <li><?php echo esc_html($b); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        <div class="meta-row reveal">
          <?php if ($svc['price']) : ?><span class="meta-pill"><?php echo esc_html($svc['price']); ?></span><?php endif; ?>
          <?php if ($svc['term']) : ?><span class="meta-pill"><?php echo esc_html($svc['term']); ?></span><?php endif; ?>
        </div>
      </div>
      <aside class="service-aside reveal" id="order">
        <h2>Заказать услугу</h2>
        <p class="muted tiny">Оставьте заявку — ответим с оценкой и следующим шагом.</p>
        <?php mb2_render_lead_form($slug); ?>
      </aside>
    </div>
  </div>
</section>
<?php get_footer(); ?>
