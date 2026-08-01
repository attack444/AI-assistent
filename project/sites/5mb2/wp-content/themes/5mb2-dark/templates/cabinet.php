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
$onb    = $logged ? mb2_get_onboarding($user->ID) : '';
$welcome = $logged && (isset($_GET['welcome']) || $onb !== 'done');
$auth_mode = (isset($_GET['reg']) || (isset($_GET['mode']) && $_GET['mode'] === 'register')) ? 'register' : 'login';

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

$services = function_exists('mb2_services_catalog') ? mb2_services_catalog() : [];

// Шаги онбординга для пользователя
$steps = [
    'profile' => [
        'n' => 1,
        'title' => 'Данные проекта',
        'text'  => 'Укажите сайт и телефон — так мы быстрее подготовим оценку.',
    ],
    'request' => [
        'n' => 2,
        'title' => 'Первая заявка',
        'text'  => 'Коротко опишите задачу. Мы ответим и откроем рабочий чеклист.',
    ],
    'done' => [
        'n' => 3,
        'title' => 'Ждите ответ',
        'text'  => 'Следите за прогрессом здесь. Статусы обновляет специалист 5MB2.',
    ],
];
?>

<section class="section cabinet-hero">
  <div class="wrap">
    <header class="section-head reveal">
      <h1><?php echo $logged ? 'Личный кабинет' : 'Вход в кабинет'; ?></h1>
      <p><?php
        echo $logged
            ? 'Здесь ведётся ваш SEO-проект: шаги, заявки и отчёты.'
            : 'Войдите в кабинет клиента. Нет аккаунта — создайте за минуту.';
      ?></p>
    </header>

    <?php if (!$logged) : ?>
      <div class="auth-shell reveal">
        <div class="auth-panel auth-panel--single">
          <div class="auth-tabs" role="tablist">
            <button type="button" class="auth-tab<?php echo $auth_mode === 'login' ? ' is-active' : ''; ?>" data-auth-tab="login" role="tab" aria-selected="<?php echo $auth_mode === 'login' ? 'true' : 'false'; ?>">Вход</button>
            <button type="button" class="auth-tab<?php echo $auth_mode === 'register' ? ' is-active' : ''; ?>" data-auth-tab="register" role="tab" aria-selected="<?php echo $auth_mode === 'register' ? 'true' : 'false'; ?>">Регистрация</button>
          </div>

          <form class="auth-form" id="mb2-login" data-auth="login" <?php echo $auth_mode === 'register' ? 'hidden' : ''; ?>>
            <label>Email
              <input type="email" name="email" required autocomplete="email" placeholder="name@company.ru" />
            </label>
            <label>Пароль
              <input type="password" name="password" required minlength="8" autocomplete="current-password" />
            </label>
            <p class="auth-error" hidden></p>
            <button class="btn btn-primary btn-block" type="submit">Войти</button>
            <p class="auth-switch muted tiny">Нет аккаунта? <button type="button" class="text-link auth-switch-btn" data-auth-tab="register">Зарегистрироваться</button></p>
          </form>

          <form class="auth-form" id="mb2-register" data-auth="register" <?php echo $auth_mode === 'login' ? 'hidden' : ''; ?>>
            <label>Имя
              <input type="text" name="name" autocomplete="name" placeholder="Как к вам обращаться" />
            </label>
            <label>Email
              <input type="email" name="email" required autocomplete="email" placeholder="name@company.ru" />
            </label>
            <label>Пароль
              <input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="минимум 8 символов" />
            </label>
            <p class="auth-error" hidden></p>
            <button class="btn btn-primary btn-block" type="submit">Создать кабинет</button>
            <p class="muted tiny" style="margin-top:12px">После регистрации покажем 3 шага: сайт → заявка → работа по чеклисту.</p>
            <p class="auth-switch muted tiny">Уже есть аккаунт? <button type="button" class="text-link auth-switch-btn" data-auth-tab="login">Войти</button></p>
          </form>
        </div>

        <aside class="auth-aside">
          <h2>Зачем кабинет</h2>
          <ol class="auth-benefits">
            <li><strong>Один вход</strong> — сайт, заявки и прогресс SEO в одном месте</li>
            <li><strong>Прозрачный чеклист</strong> — видно, что уже сделано</li>
            <li><strong>Приоритет ответа</strong> — обращения из кабинета обрабатываем первыми</li>
          </ol>
          <p class="muted tiny">Без аккаунта можно <a class="text-link" href="<?php echo esc_url(home_url('/instrumenty/')); ?>">открыть инструменты</a> или <a class="text-link" href="<?php echo esc_url(home_url('/#contact')); ?>">оставить заявку</a> на сайте.</p>
        </aside>
      </div>

    <?php else : ?>
      <?php if ($welcome && $onb !== 'done') : ?>
        <div class="onboard reveal" data-onboard="<?php echo esc_attr($onb); ?>">
          <header class="onboard-head">
            <p class="muted tiny"><?php echo isset($_GET['welcome']) ? 'Добро пожаловать в кабинет' : 'Завершите настройку'; ?></p>
            <h2>Что делать дальше</h2>
            <p class="muted">Три коротких шага — и мы сможем приступить к вашей задаче.</p>
          </header>

          <ol class="onboard-steps">
            <?php
            $order = ['profile' => 0, 'request' => 1, 'done' => 2];
            $cur_i = $order[$onb] ?? 0;
            foreach ($steps as $key => $st) :
                $i = $order[$key];
                if ($i < $cur_i) {
                    $state = 'done';
                } elseif ($i === $cur_i) {
                    $state = 'current';
                } else {
                    $state = 'todo';
                }
                ?>
              <li class="onboard-step is-<?php echo esc_attr($state); ?>">
                <span class="onboard-n"><?php echo (int) $st['n']; ?></span>
                <div>
                  <strong><?php echo esc_html($st['title']); ?></strong>
                  <p><?php echo esc_html($st['text']); ?></p>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>

          <?php if ($onb === 'profile') : ?>
            <form class="onboard-card" id="mb2-onboard-profile">
              <h3>Шаг 1 · Данные проекта</h3>
              <label>Имя
                <input type="text" name="name" value="<?php echo esc_attr($user->display_name); ?>" autocomplete="name" />
              </label>
              <label>Телефон
                <input type="tel" name="phone" value="<?php echo esc_attr($phone); ?>" autocomplete="tel" placeholder="+7 …" required />
              </label>
              <label>URL сайта
                <input type="url" name="site_url" value="<?php echo esc_attr($site); ?>" placeholder="https://example.ru" required />
              </label>
              <p class="auth-error" hidden></p>
              <p class="auth-ok" hidden></p>
              <button class="btn btn-primary" type="submit">Сохранить и дальше</button>
            </form>
          <?php elseif ($onb === 'request') : ?>
            <form class="onboard-card" id="mb2-onboard-request">
              <h3>Шаг 2 · Первая заявка</h3>
              <label>Что нужно?
                <select name="service">
                  <option value="">Выберите услугу (необязательно)</option>
                  <?php foreach ($services as $slug => $svc) : ?>
                    <option value="<?php echo esc_attr($svc['title']); ?>"><?php echo esc_html($svc['title']); ?> — <?php echo esc_html($svc['price']); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Кратко о задаче
                <textarea name="message" required minlength="10" placeholder="Например: сайт услуг в Казани, нужен аудит и оценка бюджета на 3 месяца"></textarea>
              </label>
              <p class="muted tiny">Сайт в заявке: <strong><?php echo $site ? esc_html($site) : 'не указан'; ?></strong>
                · <button type="button" class="text-link" data-cab-tab="project" id="mb2-edit-site-link">изменить</button>
              </p>
              <p class="auth-error" hidden></p>
              <p class="auth-ok" hidden></p>
              <button class="btn btn-primary" type="submit">Отправить заявку</button>
            </form>
          <?php else : ?>
            <div class="onboard-card onboard-card--done">
              <h3>Шаг 3 · Мы на связи</h3>
              <p>Заявка получена. Обычно отвечаем в рабочие часы: уточним бриф и согласуем следующий шаг. Прогресс появится в чеклисте ниже.</p>
              <div class="cta-row">
                <button type="button" class="btn btn-primary" data-cab-tab="overview" id="mb2-goto-overview">Смотреть кабинет</button>
                <a class="btn btn-ghost" href="<?php echo esc_url(home_url('/instrumenty/')); ?>">SEO-инструменты</a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

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
            <button type="button" data-cab-tab="help">Помощь</button>
          </nav>
          <a class="btn btn-ghost btn-block" href="<?php echo esc_url(home_url('/instrumenty/')); ?>">SEO-инструменты</a>
          <button class="btn btn-ghost btn-block" type="button" id="mb2-logout">Выйти</button>
        </aside>

        <div class="cabinet-main">
          <div class="cabinet-panel reveal" data-cab-panel="overview">
            <h3>Обзор проекта</h3>

            <?php if ($onb !== 'done') : ?>
              <div class="next-step">
                <p class="muted tiny">Ваш следующий шаг</p>
                <?php if ($onb === 'profile') : ?>
                  <strong>Укажите сайт и телефон</strong>
                  <p class="muted tiny">Без этого не начнём оценку. Форма выше на этой странице.</p>
                <?php elseif ($onb === 'request') : ?>
                  <strong>Отправьте первую заявку</strong>
                  <p class="muted tiny">Опишите задачу — откроем работу по проекту.</p>
                <?php endif; ?>
              </div>
            <?php elseif (!$site) : ?>
              <div class="next-step">
                <p class="muted tiny">Ваш следующий шаг</p>
                <strong>Добавьте URL сайта</strong>
                <p class="muted tiny"><button type="button" class="text-link" data-cab-tab="project">Открыть данные проекта</button></p>
              </div>
            <?php elseif ($next) : ?>
              <div class="next-step">
                <p class="muted tiny">В работе у 5MB2</p>
                <strong><?php echo esc_html($next['label'] ?? ''); ?></strong>
                <p class="muted tiny">Статус обновляем мы. Вопросы — во вкладке «Заявка».</p>
              </div>
            <?php else : ?>
              <div class="next-step is-done">
                <strong>Чеклист за период закрыт</strong>
                <p class="muted tiny">Ждите отчёт или напишите новую задачу.</p>
              </div>
            <?php endif; ?>

            <div class="status-row">
              <div class="status-pill">
                <strong><?php echo esc_html($progress); ?>%</strong>
                <span>прогресс чеклиста</span>
              </div>
              <div class="status-pill">
                <strong><?php echo $site ? 'сайт' : '—'; ?></strong>
                <span><?php echo $site ? esc_html(wp_parse_url($site, PHP_URL_HOST) ?: $site) : 'укажите сайт'; ?></span>
              </div>
              <div class="status-pill">
                <strong><?php echo count($reqs); ?></strong>
                <span>обращений</span>
              </div>
            </div>

            <?php if ($notes) : ?>
              <div class="client-note">
                <p class="muted tiny">Комментарий специалиста</p>
                <p><?php echo esc_html($notes); ?></p>
              </div>
            <?php endif; ?>

            <h4 class="cab-sub">Чеклист SEO</h4>
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
            <p class="muted tiny">Статусы обновляет команда 5MB2 по мере работы.</p>
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
              <p class="muted">Пока нет отчётов. Они появятся после старта работ — ссылкой на документ или PDF.</p>
            <?php endif; ?>
          </div>

          <div class="cabinet-panel reveal" data-cab-panel="request" hidden>
            <h3>Новая заявка</h3>
            <form id="mb2-request-form">
              <label>Услуга
                <select name="service">
                  <option value="">Не выбрано</option>
                  <?php foreach ($services as $slug => $svc) : ?>
                    <option value="<?php echo esc_attr($svc['title']); ?>"><?php echo esc_html($svc['title']); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Что нужно сделать?
                <textarea name="message" required placeholder="Опишите задачу, регион и срок, если есть"></textarea>
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
                      <strong><?php echo esc_html($r['at'] ?? ''); ?></strong>
                      <?php if (!empty($r['service'])) : ?>
                        · <?php echo esc_html($r['service']); ?>
                      <?php endif; ?>
                      <br><?php echo esc_html($r['message'] ?? ''); ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

          <div class="cabinet-panel reveal" data-cab-panel="help" hidden>
            <h3>Как пользоваться кабинетом</h3>
            <ol class="help-list">
              <li><strong>Проект</strong> — держите актуальные сайт и телефон.</li>
              <li><strong>Заявка</strong> — пишите сюда новые задачи и вопросы.</li>
              <li><strong>Обзор</strong> — смотрите чеклист: «ожидает / в работе / готово».</li>
              <li><strong>Отчёты</strong> — сюда кладём ссылки на результаты месяца.</li>
            </ol>
            <p class="muted">Нужна консультация до старта? <a class="text-link" href="<?php echo esc_url(home_url('/services/')); ?>">Смотрите услуги и цены</a> или напишите заявку.</p>
            <ul class="perk-list" style="margin-top:20px">
              <li><strong>Бесплатные инструменты</strong> — meta, UTM, бюджет на <a class="text-link" href="<?php echo esc_url(home_url('/instrumenty/')); ?>">/instrumenty/</a></li>
              <li><strong>Приоритет ответа</strong> — обращения из кабинета первыми</li>
            </ul>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php get_footer(); ?>
