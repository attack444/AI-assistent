<?php
/**
 * Template Name: Проекты
 */
get_header();
$q = new WP_Query([
    'post_type'      => 'post',
    'posts_per_page' => 12,
    'category_name'  => 'kejsy',
]);
$kind_labels = [
    'client' => 'Проект',
    'edu'    => 'Учебный разбор',
    'own'    => 'Живой проект',
];
?>
<section class="page-shell">
  <div class="wrap">
    <header class="section-head reveal">
      <h1>Проекты и разборы</h1>
      <p>Реальные работы и честные учебные разборы метода. Без выдуманных «+400% за месяц».</p>
    </header>
    <?php if ($q->have_posts()) : ?>
      <div class="post-grid">
        <?php while ($q->have_posts()) : $q->the_post();
            $kind = get_post_meta(get_the_ID(), '_mb2_project_kind', true) ?: 'client';
            $label = $kind_labels[$kind] ?? 'Проект';
            ?>
          <article class="post-card reveal">
            <span class="muted tiny"><?php echo esc_html($label); ?> · <?php echo esc_html(get_the_date('Y')); ?></span>
            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <p><?php echo esc_html(wp_trim_words(get_the_excerpt() ?: wp_strip_all_tags(get_the_content()), 36)); ?></p>
            <a class="text-link" href="<?php the_permalink(); ?>">Смотреть</a>
          </article>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>
    <?php else : ?>
      <p class="muted">Проекты появятся после синхронизации темы.</p>
    <?php endif; ?>
    <div class="tools-cta reveal" style="margin-top:36px">
      <h2>Нет десятка кейсов — есть метод</h2>
      <p>Пока копилка клиентских проектов растёт, вы видите процесс: аудит → план → кабинет. Первый совместный кейс можем оформить так же прозрачно.</p>
      <div class="cta-row">
        <a class="btn btn-primary" href="<?php echo esc_url(home_url('/#contact')); ?>">Обсудить задачу</a>
        <a class="btn btn-ghost" href="<?php echo esc_url(home_url('/instrumenty/')); ?>">Попробовать инструменты</a>
      </div>
    </div>
  </div>
</section>
<?php get_footer(); ?>
