<?php
get_header();
?>
<section class="page-shell">
  <div class="wrap narrow">
    <?php while (have_posts()) : the_post(); ?>
      <?php if (function_exists('mb2_render_breadcrumbs')) { mb2_render_breadcrumbs(); } ?>
      <article <?php post_class('prose reveal is-in'); ?> style="margin-top:16px">
        <p class="muted tiny"><?php echo esc_html(get_the_date('d.m.Y')); ?>
          <?php
          $cats = get_the_category();
          if ($cats) {
              echo ' · <a class="text-link" href="' . esc_url(get_category_link($cats[0]->term_id)) . '">' . esc_html($cats[0]->name) . '</a>';
          }
          ?>
        </p>
        <h1><?php the_title(); ?></h1>
        <?php if (has_post_thumbnail()) : ?>
          <figure class="media-frame" style="margin:18px 0 24px">
            <?php the_post_thumbnail('large', ['loading' => 'lazy', 'alt' => esc_attr(get_the_title())]); ?>
          </figure>
        <?php endif; ?>
        <?php the_content(); ?>
        <p style="margin-top:2rem">
          <a class="btn btn-primary" href="<?php echo esc_url(home_url('/#contact')); ?>">Обсудить задачу</a>
          <a class="btn btn-ghost" href="<?php echo esc_url(home_url('/materialy/')); ?>">Все материалы</a>
        </p>
      </article>
      <?php
      $related = new WP_Query([
          'post_type'      => 'post',
          'posts_per_page' => 3,
          'post__not_in'   => [get_the_ID()],
          'category__in'   => wp_list_pluck(get_the_category(), 'term_id'),
      ]);
      if ($related->have_posts()) :
          ?>
        <aside style="margin-top:40px">
          <h2 class="section-head" style="margin-bottom:16px">Читайте также</h2>
          <div class="post-grid">
            <?php while ($related->have_posts()) : $related->the_post(); ?>
              <article class="post-card">
                <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <p><?php echo esc_html(wp_trim_words(get_the_excerpt() ?: wp_strip_all_tags(get_the_content()), 18)); ?></p>
              </article>
            <?php endwhile; wp_reset_postdata(); ?>
          </div>
        </aside>
      <?php endif; ?>
    <?php endwhile; ?>
  </div>
</section>
<?php get_footer(); ?>
