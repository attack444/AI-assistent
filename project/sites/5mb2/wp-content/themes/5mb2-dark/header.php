<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="theme-color" content="#060806" />
  <meta name="format-detection" content="telephone=no" />
  <?php wp_head(); ?>
</head>
<body <?php body_class('mb2-body'); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">К содержанию</a>
<header class="site-header" data-elevate>
  <div class="wrap header-inner">
    <a class="logo" href="<?php echo esc_url(home_url('/')); ?>">
      <span class="logo-mark" aria-hidden="true"></span>
      <span class="logo-text">5MB2<span>Digital</span></span>
    </a>
    <nav class="nav" aria-label="Главное меню">
      <?php
      if (has_nav_menu('primary')) {
          wp_nav_menu([
              'theme_location' => 'primary',
              'container'      => false,
              'menu_class'     => 'nav-list',
              'depth'          => 1,
              'fallback_cb'    => 'mb2_nav_fallback',
          ]);
      } else {
          mb2_nav_fallback();
      }
      ?>
    </nav>
    <div class="header-cta">
      <?php if (is_user_logged_in()) : ?>
        <a class="btn btn-ghost" href="<?php echo esc_url(home_url('/cabinet/')); ?>">Кабинет</a>
      <?php else : ?>
        <a class="btn btn-ghost" href="<?php echo esc_url(home_url('/cabinet/')); ?>">Войти</a>
      <?php endif; ?>
      <a class="btn btn-primary" href="<?php echo esc_url(home_url('/#contact')); ?>">Заявка</a>
    </div>
    <button class="nav-toggle" type="button" aria-label="Меню" aria-expanded="false" data-nav-toggle>
      <span></span><span></span>
    </button>
  </div>
</header>
<main id="main">
