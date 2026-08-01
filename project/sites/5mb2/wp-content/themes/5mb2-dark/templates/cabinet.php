<?php
/**
 * Template Name: Личный кабинет
 * Client portal — auth + SEO dashboard placeholders.
 */
get_header();

$logged = is_user_logged_in();
$user   = $logged ? wp_get_current_user() : null;
$plan   = $logged ? (get_user_meta($user->ID, 'mb2_plan', true) ?: 'start') : '';
$site   = $logged ? (get_user_meta($user->ID, 'mb2_site_url', true) ?: '') : '';
?>

<section class="section cabinet-hero">
  <div class="wrap">
    <header class="section-head reveal">
      <h1>Личный кабинет</h1>
      <p><?php echo $logged ? 'Статус проекта и быстрые действия.' : 'Войдите или создайте аккаунт клиента.'; ?></p>
    </header>

    <?php if (!$logged) : ?>
      <div class="auth-grid reveal">
        <form class="auth-panel" id="mb2-login" data-auth="login">
          <h2>Вход</h2>
          <label>Email<input type="email" name="email" required autocomplete="email" /></label>
          <label>Пароль<input type="password" name="password" required minlength="8" autocomplete="current-password" /></label>
          <p class="auth-error" hidden></p>
          <button class="btn btn-primary" type="submit">Войти</button>
        </form>
        <form class="auth-panel" id="mb2-register" data-auth="register">
          <h2>Регистрация</h2>
          <label>Имя<input type="text" name="name" autocomplete="name" /></label>
          <label>Email<input type="email" name="email" required autocomplete="email" /></label>
          <label>Пароль<input type="password" name="password" required minlength="8" autocomplete="new-password" /></label>
          <p class="auth-error" hidden></p>
          <button class="btn btn-primary" type="submit">Создать кабинет</button>
        </form>
      </div>
    <?php else : ?>
      <div class="cabinet-grid">
        <aside class="cabinet-side reveal">
          <p class="muted">Клиент</p>
          <h2><?php echo esc_html($user->display_name ?: $user->user_email); ?></h2>
          <p class="mono"><?php echo esc_html($user->user_email); ?></p>
          <p>План: <strong><?php echo esc_html(strtoupper($plan)); ?></strong></p>
          <button class="btn btn-ghost" type="button" id="mb2-logout">Выйти</button>
        </aside>
        <div class="cabinet-main">
          <article class="card reveal">
            <h3>Ваш сайт</h3>
            <form id="mb2-site-form">
              <label>URL проекта
                <input type="url" name="site_url" placeholder="https://example.ru" value="<?php echo esc_attr($site); ?>" />
              </label>
              <p class="muted tiny">Сохраняется в профиле (скоро — задачи и отчёты).</p>
              <button class="btn btn-primary" type="submit">Сохранить</button>
              <p class="auth-ok" hidden>Сохранено</p>
            </form>
          </article>
          <article class="card reveal">
            <h3>Чеклист SEO</h3>
            <ul class="check-list">
              <li>Технический аудит</li>
              <li>Семантическое ядро</li>
              <li>Структура и мета</li>
              <li>Контент-план</li>
              <li>Ссылочный профиль</li>
              <li>Ежемесячный отчёт</li>
            </ul>
          </article>
          <article class="card reveal">
            <h3>Быстрые действия</h3>
            <div class="cta-actions">
              <a class="btn btn-primary" href="<?php echo esc_url(home_url('/#contact')); ?>">Новая заявка</a>
              <a class="btn btn-ghost" href="<?php echo esc_url(home_url('/#services')); ?>">Услуги</a>
            </div>
          </article>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php get_footer(); ?>
