<?php
/**
 * Template Name: Кейсы
 */
get_header();
$q = new WP_Query([
    'post_type'      => 'post',
    'posts_per_page' => 12,
    'category_name'  => 'kejsy',
]);
?>
<section class="page-shell">
  <div class="wrap">
    <header class="section-head reveal">
      <h1>Кейсы</h1>
      <p>Ориентиры по результатам. Точные цифры зависят от ниши и выполнения рекомендаций.</p>
    </header>
    <?php if ($q->have_posts()) : ?>
      <div class="post-grid">
        <?php while ($q->have_posts()) : $q->the_post(); ?>
          <article class="post-card reveal">
            <span class="muted tiny">Кейс · <?php echo esc_html(get_the_date('Y')); ?></span>
            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <p><?php echo esc_html(wp_trim_words(get_the_excerpt() ?: wp_strip_all_tags(get_the_content()), 32)); ?></p>
            <a class="text-link" href="<?php the_permalink(); ?>">Подробнее</a>
          </article>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>
    <?php else : ?>
      <p class="muted">Кейсы в подготовке — оставьте заявку, пришлём релевантные примеры по нише.</p>
    <?php endif; ?>
    <p style="margin-top:36px"><a class="btn btn-primary" href="<?php echo esc_url(home_url('/#contact')); ?>">Обсудить ваш проект</a></p>
  </div>
</section>
<?php get_footer(); ?>
