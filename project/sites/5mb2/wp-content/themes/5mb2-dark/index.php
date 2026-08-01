<?php get_header(); ?>
<section class="section">
  <div class="wrap">
    <header class="section-head">
      <h1><?php echo is_home() ? 'Блог' : 'Материалы'; ?></h1>
      <p class="muted">Экспертиза по SEO и продвижению</p>
    </header>
    <div class="cards-3">
      <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article class="card reveal">
          <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
          <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 22)); ?></p>
          <a class="text-link" href="<?php the_permalink(); ?>">Читать →</a>
        </article>
      <?php endwhile; else : ?>
        <p class="muted">Записей пока нет.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php get_footer(); ?>
