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
$ov = $logged ? mb2_cabinet_overview_data($user->ID) : null;

if (!is_array($reqs)) {
    $reqs = [];
}
if (!is_array($reports)) {
    $reports = [];
}

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
            ? 'Один экран: статус проекта, прогресс, следующий шаг и отчёты.'
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

      <?php
        $ov_next = $ov['next'] ?? [];
        $ov_prog = $ov['progress'] ?? ['done' => 0, 'total' => 1, 'pct' => 0];
        $ov_kpis = $ov['kpis'] ?? [];
        $has_custom_kpi = ($ov_kpis['organic'] ?? '') !== '' || ($ov_kpis['keywords'] ?? '') !== '' || ($ov_kpis['leads'] ?? '') !== '';
      ?>
      <div class="cabinet-grid">
        <aside class="cabinet-side reveal">
          <div class="cabinet-side-top">
            <div>
              <p class="muted tiny">Клиент</p>
              <h2><?php echo esc_html($user->display_name ?: $user->user_email); ?></h2>
              <p class="mono"><?php echo esc_html($user->user_email); ?></p>
            </div>
            <div class="cabinet-badges">
              <span class="plan-badge"><?php echo esc_html($plan_label); ?></span>
              <?php if (!empty($ov['phase_label'])) : ?>
                <span class="phase-badge"><?php echo esc_html($ov['phase_label']); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <nav class="cabinet-nav" aria-label="Разделы кабинета">
            <button type="button" class="is-active" data-cab-tab="overview">Обзор</button>
            <button type="button" data-cab-tab="project">Проект</button>
            <button type="button" data-cab-tab="reports">Отчёты</button>
            <button type="button" data-cab-tab="request">Заявка</button>
            <button type="button" data-cab-tab="help">Помощь</button>
          </nav>
          <div class="cabinet-side-actions">
            <a class="btn btn-ghost btn-block" href="<?php echo esc_url(home_url('/instrumenty/')); ?>">SEO-инструменты</a>
            <button class="btn btn-ghost btn-block" type="button" id="mb2-logout">Выйти</button>
          </div>
        </aside>

        <div class="cabinet-main">
          <div class="cabinet-panel cabinet-panel--overview reveal" data-cab-panel="overview">
            <header class="ov-head">
              <div>
                <p class="muted tiny">Обзор проекта</p>
                <h3><?php echo $ov['site_host'] ? esc_html($ov['site_host']) : 'Ваш SEO-проект'; ?></h3>
              </div>
              <span class="phase-badge phase-badge--lg"><?php echo esc_html($ov['phase_label'] ?? ''); ?></span>
            </header>

            <section class="ov-summary" aria-label="Краткая сводка">
              <p class="muted tiny">За 30 секунд</p>
              <p class="ov-summary-text"><?php echo esc_html($ov['summary'] ?? ''); ?></p>
              <?php if ($notes && $notes !== ($ov['summary'] ?? '')) : ?>
                <p class="ov-note"><span class="muted tiny">От специалиста:</span> <?php echo esc_html($notes); ?></p>
              <?php endif; ?>
            </section>

            <section class="ov-progress" aria-label="Прогресс работ">
              <div class="ov-progress-meta">
                <span>Прогресс работ</span>
                <strong><?php echo (int) $ov_prog['done']; ?>/<?php echo (int) $ov_prog['total']; ?> · <?php echo (int) $ov_prog['pct']; ?>%</strong>
              </div>
              <div class="ov-bar" role="progressbar" aria-valuenow="<?php echo (int) $ov_prog['pct']; ?>" aria-valuemin="0" aria-valuemax="100">
                <i style="width:<?php echo (int) $ov_prog['pct']; ?>%"></i>
              </div>
            </section>

            <div class="status-row ov-kpis">
              <?php if ($has_custom_kpi) : ?>
                <?php if (($ov_kpis['organic'] ?? '') !== '') : ?>
                  <div class="status-pill">
                    <strong><?php echo esc_html($ov_kpis['organic']); ?></strong>
                    <span>органика</span>
                  </div>
                <?php endif; ?>
                <?php if (($ov_kpis['keywords'] ?? '') !== '') : ?>
                  <div class="status-pill">
                    <strong><?php echo esc_html($ov_kpis['keywords']); ?></strong>
                    <span>запросы</span>
                  </div>
                <?php endif; ?>
                <?php if (($ov_kpis['leads'] ?? '') !== '') : ?>
                  <div class="status-pill">
                    <strong><?php echo esc_html($ov_kpis['leads']); ?></strong>
                    <span>лиды</span>
                  </div>
                <?php endif; ?>
              <?php else : ?>
                <div class="status-pill">
                  <strong><?php echo (int) $ov_prog['pct']; ?>%</strong>
                  <span>чеклист</span>
                </div>
                <div class="status-pill">
                  <strong><?php echo $site ? 'сайт' : '—'; ?></strong>
                  <span><?php echo $site ? esc_html($ov['site_host']) : 'укажите сайт'; ?></span>
                </div>
                <div class="status-pill">
                  <strong><?php echo count($reqs); ?></strong>
                  <span>обращений</span>
                </div>
                <div class="status-pill">
                  <strong><?php echo count($reports); ?></strong>
                  <span>отчётов</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="next-step<?php echo !empty($ov_next['done']) ? ' is-done' : ''; ?>">
              <p class="muted tiny"><?php echo esc_html($ov_next['eyebrow'] ?? 'Следующий шаг'); ?></p>
              <strong><?php echo esc_html($ov_next['title'] ?? ''); ?></strong>
              <?php if (!empty($ov_next['text'])) : ?>
                <p class="muted tiny"><?php echo esc_html($ov_next['text']); ?></p>
              <?php endif; ?>
              <?php if (!empty($ov_next['cta']) && !empty($ov_next['tab'])) : ?>
                <p class="next-step-cta">
                  <button type="button" class="btn btn-primary btn-sm" data-cab-tab="<?php echo esc_attr($ov_next['tab']); ?>"><?php echo esc_html($ov_next['cta']); ?></button>
                </p>
              <?php endif; ?>
            </div>

            <div class="ov-grid">
              <section class="ov-block" aria-label="Журнал работ">
                <h4>Журнал работ</h4>
                <?php if (!empty($ov['log'])) : ?>
                  <ul class="ov-log">
                    <?php foreach ($ov['log'] as $item) :
                        $t = $item['type'] ?? 'todo';
                        ?>
                      <li class="is-<?php echo esc_attr($t); ?>">
                        <span class="ov-log-mark" aria-hidden="true"></span>
                        <span>
                          <strong><?php echo esc_html($item['title'] ?? ''); ?></strong>
                          <?php if (!empty($item['meta'])) : ?>
                            <br><span class="muted tiny"><?php echo esc_html($item['meta']); ?></span>
                          <?php endif; ?>
                        </span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php else : ?>
                  <p class="muted tiny">После первой заявки здесь появятся статусы работ.</p>
                <?php endif; ?>
              </section>

              <section class="ov-block" aria-label="Последний отчёт">
                <h4>Последний отчёт</h4>
                <?php if (!empty($ov['latest_report'])) :
                    $lr = $ov['latest_report'];
                    ?>
                  <p><strong><?php echo esc_html($lr['title'] ?? 'Отчёт'); ?></strong></p>
                  <?php if (!empty($lr['at'])) : ?>
                    <p class="muted tiny"><?php echo esc_html($lr['at']); ?></p>
                  <?php endif; ?>
                  <?php if (!empty($lr['note'])) : ?>
                    <p class="muted tiny"><?php echo esc_html($lr['note']); ?></p>
                  <?php endif; ?>
                  <p class="cta-row" style="margin-top:12px">
                    <?php if (!empty($lr['url'])) : ?>
                      <a class="btn btn-primary btn-sm" href="<?php echo esc_url($lr['url']); ?>" target="_blank" rel="noopener">Открыть</a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-ghost btn-sm" data-cab-tab="reports">Все отчёты</button>
                  </p>
                <?php else : ?>
                  <p class="muted tiny">Отчёты появятся после старта работ — ссылкой на документ или PDF. Обычно раз в месяц.</p>
                  <button type="button" class="btn btn-ghost btn-sm" data-cab-tab="reports" style="margin-top:10px">Раздел отчётов</button>
                <?php endif; ?>
              </section>
            </div>

            <h4 class="cab-sub">Чеклист SEO</h4>
            <ul class="check-list">
              <?php foreach ($checks as $c) :
                  $st = $c['status'] ?? 'todo';
                  $cls = $st === 'done' ? 'is-done' : ($st === 'progress' ? 'is-progress' : '');
                  $st_label = $st === 'done' ? 'Готово' : ($st === 'progress' ? 'В работе' : 'Ожидает');
                  $label = (string) ($c['label'] ?? '');
                  if (function_exists('mb2_checklist_label_broken') && mb2_checklist_label_broken($label)) {
                      $label = 'Пункт работ';
                  }
                  ?>
                <li class="<?php echo esc_attr($cls); ?>">
                  <span class="dot" aria-hidden="true"></span>
                  <span class="check-body">
                    <span class="check-label"><?php echo esc_html($label); ?></span>
                    <span class="check-st"><?php echo esc_html($st_label); ?></span>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>

            <aside class="ov-expect">
              <p>SEO — накопительный канал: заметные сдвиги обычно через 2–4 месяца после базы. Здесь видно, что уже сделано и что в очереди — без сюрпризов в переписке.</p>
            </aside>
          </div>

          <div class="cabinet-panel reveal" data-cab-panel="project" hidden>
            <h3>Данные проекта</h3>
            <p class="muted tiny" style="margin-top:-8px;margin-bottom:16px">Тариф: <strong><?php echo esc_html($plan_label); ?></strong>
              <?php if (!empty($ov['phase_label'])) : ?>
                · фаза: <strong><?php echo esc_html($ov['phase_label']); ?></strong>
              <?php endif; ?>
            </p>
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
            <p class="muted tiny" style="margin-top:-8px;margin-bottom:16px">Сюда кладём итоги периода: что сделали, что изменилось, что дальше.</p>
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
                        <br><a class="text-link" href="<?php echo esc_url($r['url']); ?>" target="_blank" rel="noopener">Открыть</a>
                      <?php endif; ?>
                      <?php if (!empty($r['note'])) : ?>
                        <br><?php echo esc_html($r['note']); ?>
                      <?php endif; ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else : ?>
              <div class="ov-block">
                <p class="muted">Пока нет отчётов. Они появятся после старта работ — ссылкой на документ или PDF. Пока смотрите прогресс во вкладке «Обзор».</p>
                <button type="button" class="btn btn-ghost btn-sm" data-cab-tab="overview" style="margin-top:12px">К обзору</button>
              </div>
            <?php endif; ?>
          </div>

          <div class="cabinet-panel reveal" data-cab-panel="request" hidden>
            <h3>Новая заявка</h3>
            <p class="muted tiny" style="margin-top:-8px;margin-bottom:16px">Вопросы, доступы, новые задачи — пишите сюда. Обращения из кабинета в приоритете.</p>
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
              <h4 class="cab-sub">История обращений</h4>
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
              <li><strong>Обзор</strong> — статус фазы, сводка, прогресс, следующий шаг и журнал работ.</li>
              <li><strong>Проект</strong> — актуальные сайт и телефон.</li>
              <li><strong>Заявка</strong> — новые задачи, доступы и вопросы команде.</li>
              <li><strong>Отчёты</strong> — ссылки на итоги месяца и материалы.</li>
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
