<?php
/**
 * Template Name: Спасибо за заявку
 */
get_header();
?>
<section class="page-shell">
  <div class="wrap narrow">
    <header class="section-head reveal is-in">
      <h1>Заявка принята</h1>
      <p>Спасибо! Мы свяжемся с вами по email или телефону — обычно в течение рабочего дня.</p>
    </header>
    <div class="prose reveal is-in">
      <p>Пока можно:</p>
      <ul>
        <li>создать <a class="text-link" href="<?php echo esc_url(home_url('/cabinet/')); ?>">личный кабинет</a> и указать сайт;</li>
        <li>посмотреть <a class="text-link" href="<?php echo esc_url(home_url('/services/')); ?>">услуги</a>;</li>
        <li>прочитать <a class="text-link" href="<?php echo esc_url(home_url('/materialy/')); ?>">материалы</a>.</li>
      </ul>
      <p><a class="btn btn-primary" href="<?php echo esc_url(home_url('/')); ?>">На главную</a></p>
    </div>
  </div>
</section>
<?php get_footer(); ?>
