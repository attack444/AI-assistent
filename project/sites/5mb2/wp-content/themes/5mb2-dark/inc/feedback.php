<?php
/**
 * Обратная связь: идеи, ошибки, рекомендации по сайту.
 * Форма внизу страницы (не FAB — не перекрывается чат-виджетом).
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_nopriv_mb2_feedback', 'mb2_ajax_feedback');
add_action('wp_ajax_mb2_feedback', 'mb2_ajax_feedback');

function mb2_feedback_types() {
    return [
        'idea'  => 'Идея / улучшение',
        'bug'   => 'Ошибка на сайте',
        'need'  => 'Мне нужно…',
        'other' => 'Другое',
    ];
}

function mb2_ajax_feedback() {
    check_ajax_referer('mb2_auth', 'nonce');

    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        wp_send_json_success(['message' => 'Спасибо!']);
    }

    $type = sanitize_text_field(wp_unslash($_POST['type'] ?? 'idea'));
    $types = mb2_feedback_types();
    if (!isset($types[$type])) {
        $type = 'other';
    }
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $page = esc_url_raw(wp_unslash($_POST['page'] ?? ''));
    if (strlen($message) < 8) {
        wp_send_json_error(['message' => 'Опишите чуть подробнее (от 8 символов)'], 400);
    }
    if (strlen($message) > 4000) {
        wp_send_json_error(['message' => 'Слишком длинное сообщение'], 400);
    }

    $item = [
        'at'      => current_time('mysql'),
        'type'    => $type,
        'message' => $message,
        'email'   => $email,
        'page'    => $page ?: home_url('/'),
        'ip'      => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        'source'  => '5mb2',
    ];

    $list = get_option('mb2_feedback', []);
    if (!is_array($list)) {
        $list = [];
    }
    array_unshift($list, $item);
    update_option('mb2_feedback', array_slice($list, 0, 300), false);

    $admin = get_option('admin_email');
    if ($admin) {
        wp_mail(
            $admin,
            'Обратная связь 5MB2: ' . ($types[$type] ?? $type),
            "Тип: {$types[$type]}\nEmail: {$email}\nСтраница: {$item['page']}\n\n{$message}\n"
        );
    }

    // Дублируем в inbox панели AI Helper (если /api проксируется)
    wp_remote_post(
        home_url('/api/public/feedback'),
        [
            'timeout' => 4,
            'blocking' => false,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'    => wp_json_encode([
                'type'    => $type,
                'message' => $message,
                'email'   => $email,
                'page'    => $item['page'],
                'source'  => '5mb2',
            ]),
        ]
    );

    wp_send_json_success(['message' => 'Спасибо! Сообщение получили — учтём при улучшениях.']);
}

/** Блок «что вам нужно?» — быстрые пути. */
function mb2_render_need_paths() {
    $items = [
        [
            'title' => 'Нужен SEO-аудит',
            'text'  => 'Разовая диагностика и план правок',
            'href'  => mb2_service_url('seo-audit'),
        ],
        [
            'title' => 'Хочу заявки из поиска',
            'text'  => 'Ежемесячное продвижение',
            'href'  => mb2_service_url('prodvizhenie'),
        ],
        [
            'title' => 'Бизнес в городе',
            'text'  => 'Local SEO: карты и локальная выдача',
            'href'  => mb2_service_url('local-seo'),
        ],
        [
            'title' => 'Проверить сайт сам',
            'text'  => 'Бесплатные SEO-инструменты',
            'href'  => home_url('/instrumenty/'),
        ],
        [
            'title' => 'Уже клиент',
            'text'  => 'Кабинет: прогресс и заявки',
            'href'  => home_url('/cabinet/'),
        ],
        [
            'title' => 'Идея или ошибка',
            'text'  => 'Форма ниже на этой странице',
            'href'  => '#feedback',
        ],
    ];
    ?>
    <section class="section section-alt" id="need">
      <div class="wrap">
        <header class="section-head reveal">
          <h2>Что вам нужно?</h2>
          <p>Выберите путь — сразу к услуге, инструментам или кабинету. Есть идея по сайту — форма внизу страницы.</p>
        </header>
        <div class="need-grid">
          <?php foreach ($items as $it) : ?>
            <a class="need-card reveal" href="<?php echo esc_url($it['href']); ?>">
              <strong><?php echo esc_html($it['title']); ?></strong>
              <span><?php echo esc_html($it['text']); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php
}

/**
 * Секция обратной связи перед футером (на всех страницах).
 * Не FAB: не конфликтует с чат-виджетом.
 */
function mb2_render_feedback_section() {
    if (is_admin()) {
        return;
    }
    $types = mb2_feedback_types();
    ?>
    <section class="section fb-section" id="feedback">
      <div class="wrap fb-section-inner">
        <header class="section-head reveal">
          <h2>Идея или ошибка?</h2>
          <p>Помогите сделать 5MB2 удобнее. Это не заявка на SEO — просто голос пользователя: баг, идея или «мне нужно…».</p>
        </header>
        <div class="fb-section-grid reveal">
          <ul class="fb-points">
            <li><strong>Ошибка</strong> — страница, кнопка, кабинет, мобильная вёрстка</li>
            <li><strong>Идея</strong> — чего не хватает в услугах, инструментах, текстах</li>
            <li><strong>Мне нужно…</strong> — опишите задачу, подскажем путь</li>
          </ul>
          <form class="fb-form fb-form--section" id="mb2-feedback-form" data-feedback-form>
            <label class="fb-hp" aria-hidden="true">Сайт
              <input type="text" name="website" tabindex="-1" autocomplete="off" />
            </label>
            <label>Тип
              <select name="type">
                <?php foreach ($types as $k => $lab) : ?>
                  <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($lab); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Сообщение
              <textarea name="message" required minlength="8" maxlength="4000" placeholder="Что улучшить или где ошибка? Можно со ссылкой"></textarea>
            </label>
            <label>Email (необязательно)
              <input type="email" name="email" autocomplete="email" placeholder="если нужен ответ" />
            </label>
            <input type="hidden" name="page" value="" data-feedback-page />
            <p class="form-note" hidden></p>
            <button class="btn btn-primary" type="submit">Отправить сообщение</button>
          </form>
        </div>
      </div>
    </section>
    <?php
}

add_action('wp_footer', 'mb2_render_feedback_section', 5);
