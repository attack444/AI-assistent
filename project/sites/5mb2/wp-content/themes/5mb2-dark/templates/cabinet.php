<?php
/**
 * Template Name: Личный кабинет
 */
get_header();

$logged = is_user_logged_in();
$user   = $logged ? wp_get_current_user() : null;
$plan   = $logged ? (get_user_meta($user->ID, 'mb2_plan', true) ?: 'start') : '';
$site   = $logged ? (get_user_meta($user->ID, 'mb2_site_url', true) ?: '') : '';
$phone  = $logged ? (get_user_meta($user->ID, 'mb2_phone', true) ?: '') : '';
$checks = $logged ? mb2_get_checklist($user->ID) : [];
$reqs   = $logged ? (get_user_meta($user->ID, 'mb2_requests', true) ?: []) : [];
if (!is_array($reqs)) {
    $reqs = [];
}

$done = 0;
foreach ($checks as $c) {
    if (($c['status'] ?? '') === 'done') {
        $done++;
    }
}
$total = max(count($checks), 1);
$progress = (int) round(($done / $total) * 100);
?>

<section class="section cabinet-hero">
  <div class="wrap">
    <header class="section-head reveal">
      <h1>Личный кабинет</h1>
      <p><?php echo $logged ? 'Проект, чеклист SEO и связь с командой 5MB2.' : 'Войдите или создайте аккаунт клиента — это бесплатно.'; ?></p>
    </header>

    <?php if (!$logged) : ?>
      <div class="auth-grid reveal">
        <form class="auth-panel" id="mb2-login" data-auth="login">
          <h2>Вход</h2>
          <label>Email<input type="email" name="email" required autocomplete="email" /></label>
          <label>Пароль<input type="password" name="password" required minlength="8" autocomplete="current-password" /></label>
          <p class="auth-error" hidden></p>
          <button class="btn btn-primary btn-block" type="submit">Войти</button>
        </form>
        <form class="auth-panel" id="mb2-register" data-auth="register">
          <h2>Регистрация</h2>
          <label>Имя<input type="text" name="name" autocomplete="name" placeholder="Как к вам обращаться" /></label>
          <label>Email<input type="email" name="email" required autocomplete="email" /></label>
          <label>Пароль<input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="минимум 8 символов" /></label>
          <p class="auth-error" hidden></p>
          <button class="btn btn-primary btn-block" type="submit">Создать кабинет</button>
          <p class="muted tiny" style="margin-top:12px">После регистрации откроется панель проекта.</p>
        </form>
      </div>
    <?php else : ?>
      <div class="cabinet-grid">
        <aside class="cabinet-side reveal">
          <p class="muted tiny">Клиент</p>
          <h2><?php echo esc_html($user->display_name ?: $user->user_email); ?></h2>
          <p class="mono"><?php echo esc_html($user->user_email); ?></p>
          <span class="plan-badge"><?php echo esc_html($plan); ?></span>
          <nav class="cabinet-nav" aria-label="Разделы кабинета">
            <button type="button" class="is-active" data-cab-tab="overview">Обзор</button>
            <button type="button" data-cab-tab="project">Проект</button>
            <button type="button" data-cab-tab="request">Заявка</button>
          </nav>
          <button class="btn btn-ghost btn-block" type="button" id="mb2-logout">Выйти</button>
        </aside>

        <div class="cabinet-main">
          <div class="cabinet-panel reveal" data-cab-panel="overview">
            <h3>Обзор проекта</h3>
            <div class="status-row">
              <div class="status-pill">
                <strong><?php echo esc_html($progress); ?>%</strong>
                <span>прогресс чеклиста</span>
              </div>
              <div class="status-pill">
                <strong><?php echo $site ? 'сайт' : '—'; ?></strong>
                <span><?php echo $site ? 'URL привязан' : 'укажите сайт'; ?></span>
              </div>
              <div class="status-pill">
                <strong><?php echo count($reqs); ?></strong>
                <span>обращений</span>
              </div>
            </div>
            <ul class="check-list">
              <?php foreach ($checks as $c) :
                  $st = $c['status'] ?? 'todo';
                  $cls = $st === 'done' ? 'is-done' : ($st === 'progress' ? 'is-progress' : '');
                  ?>
                <li class="<?php echo esc_attr($cls); ?>">
                  <span class="dot" aria-hidden="true"></span>
                  <span><?php echo esc_html($c['label'] ?? ''); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
            <p class="muted tiny">Статусы обновляет команда 5MB2 по мере работы над проектом.</p>
          </div>

          <div class="cabinet-panel reveal" data-cab-panel="project" hidden>
            <h3>Данные проекта</h3>
            <form id="mb2-profile-form">
              <label>Имя
                <input type="text" name="name" value="<?php echo esc_attr($user->display_name); ?>" autocomplete="name" />
              </label>
              <label>Телефон
                <input type="tel" name="phone" value="<?php echo esc_attr($phone); ?>" autocomplete="tel" placeholder="+7 …" />
              </label>
              <label>URL сайта
                <input type="url" name="site_url" value="<?php echo esc_attr($site); ?>" placeholder="https://example.ru" />
              </label>
              <button class="btn btn-primary" type="submit">Сохранить</button>
              <p class="auth-ok" hidden>Сохранено</p>
              <p class="auth-error" hidden></p>
            </form>
          </div>

          <div class="cabinet-panel reveal" data-cab-panel="request" hidden>
            <h3>Новая заявка</h3>
            <form id="mb2-request-form">
              <label>Что нужно сделать?
                <textarea name="message" required placeholder="Например: нужен аудит и оценка сроков по региону Москва" style="min-height:120px;width:100%;background:rgba(0,0,0,.35);border:1px solid var(--line);color:var(--ink);border-radius:10px;padding:12px;"></textarea>
              </label>
              <button class="btn btn-primary" type="submit">Отправить команде</button>
              <p class="auth-ok" hidden>Заявка отправлена</p>
              <p class="auth-error" hidden></p>
            </form>
            <?php if ($reqs) : ?>
              <h3 style="margin-top:28px">История</h3>
              <ul class="check-list">
                <?php foreach ($reqs as $r) : ?>
                  <li>
                    <span class="dot" aria-hidden="true"></span>
                    <span>
                      <strong><?php echo esc_html($r['at'] ?? ''); ?></strong><br>
                      <?php echo esc_html($r['message'] ?? ''); ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php get_footer(); ?>
