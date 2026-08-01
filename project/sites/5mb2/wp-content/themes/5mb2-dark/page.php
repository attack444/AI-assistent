<?php
/**
 * Default page template.
 */
get_header();
?>
<section class="page-shell">
  <div class="wrap">
    <?php while (have_posts()) : the_post(); ?>
      <article <?php post_class('prose reveal is-in'); ?>>
        <h1><?php the_title(); ?></h1>
        <?php the_content(); ?>
      </article>
    <?php endwhile; ?>
  </div>
</section>
<?php get_footer(); ?>
