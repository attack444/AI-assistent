<?php
/**
 * Обратная связь: идеи, ошибки, рекомендации по сайту.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_nopriv_mb2_feedback', 'mb2_ajax_feedback');
add_action('wp_ajax_mb2_feedback', 'mb2_ajax_feedback');

function mb2_feedback_types() {
    return [
        'idea'    => 'Идея / улучшение',
        'bug'     => 'Ошибка на сайте',
        'need'    => 'Мне нужно…',
        'other'   => 'Другое',
    ];
}

function mb2_ajax_feedback() {
    check_ajax_referer('mb2_auth', 'nonce');

    // Honeypot
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
            'text'  => 'Помогите сделать сайт лучше',
            'href'  => '#feedback',
            'attr'  => 'data-feedback-open',
        ],
    ];
    ?>
    <section class="section section-alt" id="need">
      <div class="wrap">
        <header class="section-head reveal">
          <h2>Что вам нужно?</h2>
          <p>Выберите путь — сразу к услуге, инструментам или кабинету. Есть идея по сайту — напишите нам.</p>
        </header>
        <div class="need-grid">
          <?php foreach ($items as $it) : ?>
            <a class="need-card reveal" href="<?php echo esc_url($it['href']); ?>"<?php
              echo !empty($it['attr']) ? ' data-feedback-open' : '';
            ?>>
              <strong><?php echo esc_html($it['title']); ?></strong>
              <span><?php echo esc_html($it['text']); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php
}

/** Плавающая кнопка + панель обратной связи (на всех страницах). */
function mb2_render_feedback_widget() {
    if (is_admin()) {
        return;
    }
    $types = mb2_feedback_types();
    ?>
    <div class="fb-root" id="feedback" data-feedback>
      <button type="button" class="fb-fab" data-feedback-open aria-expanded="false" aria-controls="mb2-feedback-panel">
        Идея / ошибка
      </button>
      <div class="fb-panel" id="mb2-feedback-panel" hidden role="dialog" aria-labelledby="mb2-fb-title">
        <header class="fb-panel-head">
          <div>
            <p class="muted tiny">Обратная связь</p>
            <h2 id="mb2-fb-title">Помогите улучшить сайт</h2>
          </div>
          <button type="button" class="fb-close" data-feedback-close aria-label="Закрыть">×</button>
        </header>
        <p class="muted tiny fb-lead">Нашли ошибку, есть идея или не хватает функции — напишите. Это не заявка на SEO, а голос пользователя.</p>
        <form class="fb-form" id="mb2-feedback-form" data-feedback-form>
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
            <textarea name="message" required minlength="8" maxlength="4000" placeholder="Что улучшить или где ошибка? Можно со ссылкой на страницу"></textarea>
          </label>
          <label>Email (необязательно)
            <input type="email" name="email" autocomplete="email" placeholder="если нужен ответ" />
          </label>
          <input type="hidden" name="page" value="" data-feedback-page />
          <p class="form-note" hidden></p>
          <button class="btn btn-primary btn-block" type="submit">Отправить</button>
        </form>
      </div>
      <div class="fb-backdrop" data-feedback-close hidden></div>
    </div>
    <?php
}

add_action('wp_footer', 'mb2_render_feedback_widget', 20);
