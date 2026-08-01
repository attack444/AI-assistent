<?php get_header(); ?>
<article class="section">
  <div class="wrap narrow content-area">
    <?php while (have_posts()) : the_post(); ?>
      <header class="section-head reveal">
        <p class="muted"><?php echo esc_html(get_the_date()); ?></p>
        <h1><?php the_title(); ?></h1>
      </header>
      <div class="prose reveal"><?php the_content(); ?></div>
    <?php endwhile; ?>
  </div>
</article>
<?php get_footer(); ?>
