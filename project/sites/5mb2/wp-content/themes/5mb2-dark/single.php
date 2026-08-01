<?php
get_header();
?>
<section class="page-shell">
  <div class="wrap">
    <?php while (have_posts()) : the_post(); ?>
      <article <?php post_class('prose reveal is-in'); ?>>
        <p class="muted tiny"><?php echo esc_html(get_the_date()); ?></p>
        <h1><?php the_title(); ?></h1>
        <?php the_content(); ?>
        <p style="margin-top:2rem"><a class="text-link" href="<?php echo esc_url(home_url('/')); ?>">← На главную</a></p>
      </article>
    <?php endwhile; ?>
  </div>
</section>
<?php get_footer(); ?>
