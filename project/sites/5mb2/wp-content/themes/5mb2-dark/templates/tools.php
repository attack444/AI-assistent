<?php
/**
 * Template Name: SEO-инструменты
 */
get_header();
?>

<section class="section tools-hero">
  <div class="wrap">
    <?php if (function_exists('mb2_render_breadcrumbs')) { mb2_render_breadcrumbs(); } ?>
    <header class="section-head reveal">
      <h1>SEO-инструменты</h1>
      <p>Бесплатные мини-утилиты до заявки: мета, UTM, бюджет, быстрая проверка URL. Без регистрации.</p>
    </header>

    <div class="tools-grid">
      <article class="tool-block reveal" id="meta">
        <h2>Проверка Title и Description</h2>
        <p class="muted tiny">Ориентиры под выдачу Яндекс/Google: title ≈ 50–60 символов, description ≈ 140–160.</p>
        <label>Title
          <input type="text" id="tool-title" maxlength="120" placeholder="Например: SEO продвижение сайтов в Москве" />
        </label>
        <div class="tool-meter" data-meter="title"><span></span></div>
        <p class="tool-hint" id="title-hint">0 символов</p>
        <label>Description
          <textarea id="tool-desc" maxlength="320" rows="3" placeholder="Кратко: что получает клиент и чем вы отличаетесь"></textarea>
        </label>
        <div class="tool-meter" data-meter="desc"><span></span></div>
        <p class="tool-hint" id="desc-hint">0 символов</p>
      </article>

      <article class="tool-block reveal" id="utm">
        <h2>Генератор UTM-меток</h2>
        <p class="muted tiny">Соберите ссылку для рекламы и аналитики — копируйте одним кликом.</p>
        <label>URL страницы
          <input type="url" id="utm-url" placeholder="https://example.ru/usluga/" />
        </label>
        <label>Источник (utm_source)
          <input type="text" id="utm-source" placeholder="yandex / telegram / newsletter" />
        </label>
        <label>Канал (utm_medium)
          <input type="text" id="utm-medium" placeholder="cpc / social / email" />
        </label>
        <label>Кампания (utm_campaign)
          <input type="text" id="utm-campaign" placeholder="seo_spring_2026" />
        </label>
        <button class="btn btn-primary" type="button" id="utm-build">Собрать ссылку</button>
        <label style="margin-top:14px">Результат
          <input type="text" id="utm-out" readonly placeholder="Готовая ссылка появится здесь" />
        </label>
        <button class="btn btn-ghost" type="button" id="utm-copy">Копировать</button>
        <p class="auth-ok" id="utm-ok" hidden>Скопировано</p>
      </article>

      <article class="tool-block reveal" id="budget">
        <h2>Калькулятор бюджета SEO</h2>
        <p class="muted tiny">Грубый ориентир по нише — не смета. Точная цифра после брифа.</p>
        <label>Тип задачи
          <select id="budget-goal">
            <option value="audit">Разовый SEO-аудит</option>
            <option value="local" selected>Local SEO / город</option>
            <option value="growth">Ежемесячное продвижение</option>
            <option value="tech">Техническое SEO</option>
          </select>
        </label>
        <label>Конкуренция
          <select id="budget-comp">
            <option value="low">Низкая (узкая ниша / малый город)</option>
            <option value="mid" selected>Средняя</option>
            <option value="high">Высокая (Москва / федерал)</option>
          </select>
        </label>
        <label>Объём сайта
          <select id="budget-size">
            <option value="s">До 30 страниц</option>
            <option value="m" selected>30–150 страниц</option>
            <option value="l">150+ страниц / каталог</option>
          </select>
        </label>
        <button class="btn btn-primary" type="button" id="budget-run">Посчитать ориентир</button>
        <div class="budget-result" id="budget-result" hidden>
          <strong id="budget-sum">—</strong>
          <p class="muted tiny" id="budget-note"></p>
          <a class="btn btn-ghost" href="<?php echo esc_url(home_url('/#contact')); ?>">Получить точный расчёт</a>
        </div>
      </article>

      <article class="tool-block reveal" id="check">
        <h2>Быстрая проверка сайта</h2>
        <p class="muted tiny">Смотрим HTTPS, sitemap.xml и robots.txt — публичные сигналы. Не замена аудиту.</p>
        <label>URL сайта
          <input type="url" id="check-url" placeholder="https://example.ru" />
        </label>
        <button class="btn btn-primary" type="button" id="check-run">Проверить</button>
        <ul class="check-list" id="check-list" hidden></ul>
        <p class="muted tiny" id="check-note" hidden>Для полного разбора — <a href="<?php echo esc_url(home_url('/services/seo-audit/')); ?>">SEO-аудит</a>.</p>
      </article>
    </div>

    <div class="tools-cta reveal">
      <h2>Нужен результат, а не галочки</h2>
      <p>Инструменты показывают базу. Стратегию и внедрение берёт 5MB2 — с чеклистом в кабинете.</p>
      <div class="cta-row">
        <a class="btn btn-primary" href="<?php echo esc_url(home_url('/#contact')); ?>">Оставить заявку</a>
        <a class="btn btn-ghost" href="<?php echo esc_url(home_url('/cabinet/')); ?>">Открыть кабинет</a>
      </div>
    </div>
  </div>
</section>

<?php get_footer(); ?>
