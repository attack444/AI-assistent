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
$reports = $logged ? (get_user_meta($user->ID, 'mb2_reports', true) ?: []) : [];
$notes  = $logged ? (get_user_meta($user->ID, 'mb2_client_note', true) ?: '') : '';
if (!is_array($reqs)) {
    $reqs = [];
}
if (!is_array($reports)) {
    $reports = [];
}

$done = 0;
$next = null;
foreach ($checks as $c) {
    if (($c['status'] ?? '') === 'done') {
        $done++;
    } elseif (!$next) {
        $next = $c;
    }
}
$total = max(count($checks), 1);
$progress = (int) round(($done / $total) * 100);

$plan_labels = [
    'start'   => 'Старт',
    'audit'   => 'Аудит',
    'monthly' => 'Ежемесячное SEO',
    'local'   => 'Local SEO',
];
$plan_label = $plan_labels[$plan] ?? $plan;
?>

<section class="section cabinet-hero">
  <div class="wrap">
    <header class="section-head reveal">
      <h1>Личный кабинет</h1>
      <p><?php echo $logged ? 'Проект, чеклист SEO, отчёты и связь с 5MB2.' : 'Войдите или создайте аккаунт клиента — бесплатно. Так удобнее вести проект.'; ?></p>
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
          <p class="muted tiny" style="margin-top:12px">После регистрации укажите сайт и оставьте заявку — откроем чеклист работ.</p>
        </form>
      </div>
      <p class="muted tiny reveal" style="margin-top:22px;text-align:center">
        Пока без аккаунта можно <a href="<?php echo esc_url(home_url('/instrumenty/')); ?>">поиграть с SEO-инструментами</a>
        или <a href="<?php echo esc_url(home_url('/#contact')); ?>">оставить заявку</a>.
      </p>
    <?php else : ?>
      <div class="cabinet-grid">
        <aside class="cabinet-side reveal">
          <p class="muted tiny">Клиент</p>
          <h2><?php echo esc_html($user->display_name ?: $user->user_email); ?></h2>
          <p class="mono"><?php echo esc_html($user->user_email); ?></p>
          <span class="plan-badge"><?php echo esc_html($plan_label); ?></span>
          <nav class="cabinet-nav" aria-label="Разделы кабинета">
            <button type="button" class="is-active" data-cab-tab="overview">Обзор</button>
            <button type="button" data-cab-tab="project">Проект</button>
            <button type="button" data-cab-tab="reports">Отчёты</button>
            <button type="button" data-cab-tab="request">Заявка</button>
            <button type="button" data-cab-tab="perks">Бонусы</button>
          </nav>
          <a class="btn btn-ghost btn-block" href="<?php echo esc_url(home_url('/instrumenty/')); ?>">SEO-инструменты</a>
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

            <?php if ($next) : ?>
              <div class="next-step">
                <p class="muted tiny">Следующий шаг</p>
                <strong><?php echo esc_html($next['label'] ?? ''); ?></strong>
                <p class="muted tiny">Статус обновляет 5MB2 по мере работы. Вопросы — во вкладке «Заявка».</p>
              </div>
            <?php elseif ($done === $total) : ?>
              <div class="next-step is-done">
                <strong>Чеклист закрыт за период</strong>
                <p class="muted tiny">Ждите следующий отчёт или напишите новую задачу.</p>
              </div>
            <?php endif; ?>

            <?php if ($notes) : ?>
              <div class="client-note">
                <p class="muted tiny">Комментарий специалиста</p>
                <p><?php echo esc_html($notes); ?></p>
              </div>
            <?php endif; ?>

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
            <p class="muted tiny">Статусы обновляет команда 5MB2. Вы видите прозрачный прогресс без «магии в презентациях».</p>
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

          <div class="cabinet-panel reveal" data-cab-panel="reports" hidden>
            <h3>Отчёты и материалы</h3>
            <?php if ($reports) : ?>
              <ul class="check-list">
                <?php foreach ($reports as $r) : ?>
                  <li class="is-done">
                    <span class="dot" aria-hidden="true"></span>
                    <span>
                      <strong><?php echo esc_html($r['title'] ?? 'Отчёт'); ?></strong>
                      <?php if (!empty($r['at'])) : ?>
                        <br><span class="muted tiny"><?php echo esc_html($r['at']); ?></span>
                      <?php endif; ?>
                      <?php if (!empty($r['url'])) : ?>
                        <br><a href="<?php echo esc_url($r['url']); ?>" target="_blank" rel="noopener">Открыть</a>
                      <?php endif; ?>
                      <?php if (!empty($r['note'])) : ?>
                        <br><?php echo esc_html($r['note']); ?>
                      <?php endif; ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else : ?>
              <p class="muted">Пока нет загруженных отчётов. После первого месяца работы здесь появятся ссылки на отчёты и рекомендации.</p>
            <?php endif; ?>
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

          <div class="cabinet-panel reveal" data-cab-panel="perks" hidden>
            <h3>Бонусы клиента</h3>
            <ul class="perk-list">
              <li><strong>Чеклист в кабинете</strong> — видно, что сделано, без лишних созвонов.</li>
              <li><strong>Бесплатные SEO-инструменты</strong> — мета, UTM, ориентир бюджета.</li>
              <li><strong>Приоритет ответа</strong> — заявки из кабинета обрабатываются первыми.</li>
              <li><strong>Материалы</strong> — доступ к гайдам на сайте и отчётам по проекту.</li>
            </ul>
            <p class="muted tiny">Хотите расширить пакет — напишите во вкладке «Заявка».</p>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php get_footer(); ?>
