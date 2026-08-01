<?php
/**
 * Blog / fallback index.
 */
get_header();
?>
<section class="page-shell">
  <div class="wrap">
    <header class="section-head reveal is-in">
      <h1>Материалы</h1>
      <p>Заметки и обновления 5MB2 Digital.</p>
    </header>
    <?php if (have_posts()) : ?>
      <div class="service-list">
        <?php while (have_posts()) : the_post(); ?>
          <article <?php post_class('service-item reveal is-in'); ?>>
            <span class="service-num"><?php echo esc_html(get_the_date('d.m')); ?></span>
            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 24)); ?></p>
          </article>
        <?php endwhile; ?>
      </div>
      <div class="prose" style="margin-top:28px"><?php the_posts_pagination(); ?></div>
    <?php else : ?>
      <p class="muted">Пока нет записей.</p>
    <?php endif; ?>
  </div>
</section>
<?php get_footer(); ?>
