<?php
/**
 * Template Name: Материалы
 */
get_header();
$q = new WP_Query([
    'post_type'      => 'post',
    'posts_per_page' => 12,
    'category_name'  => 'materialy',
]);
if (!$q->have_posts()) {
    $q = new WP_Query(['post_type' => 'post', 'posts_per_page' => 12]);
}
?>
<section class="page-shell">
  <div class="wrap">
    <?php if (function_exists('mb2_render_breadcrumbs')) { mb2_render_breadcrumbs(); } ?>
    <header class="section-head reveal">
      <h1>Материалы</h1>
      <p>Заметки про SEO, локальное продвижение и запуск роста.</p>
    </header>
    <?php if ($q->have_posts()) : ?>
      <div class="post-grid">
        <?php while ($q->have_posts()) : $q->the_post(); ?>
          <article class="post-card reveal">
            <span class="muted tiny"><?php echo esc_html(get_the_date()); ?></span>
            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <p><?php echo esc_html(wp_trim_words(get_the_excerpt() ?: wp_strip_all_tags(get_the_content()), 28)); ?></p>
            <a class="text-link" href="<?php the_permalink(); ?>">Читать</a>
          </article>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>
    <?php else : ?>
      <p class="muted">Материалы скоро появятся.</p>
    <?php endif; ?>
  </div>
</section>
<?php get_footer(); ?>
