<?php get_header(); ?>
<section class="section">
  <div class="wrap narrow content-area">
    <?php while (have_posts()) : the_post(); ?>
      <header class="section-head reveal">
        <h1><?php the_title(); ?></h1>
      </header>
      <div class="prose reveal"><?php the_content(); ?></div>
    <?php endwhile; ?>
  </div>
</section>
<?php get_footer(); ?>
