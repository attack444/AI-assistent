<?php
/**
 * Homepage — NVIDIA-dark SEO agency landing.
 */
get_header();
?>

<section class="hero">
  <div class="hero-bg" aria-hidden="true">
    <div class="hero-grid"></div>
    <div class="hero-glow"></div>
    <div class="hero-orb"></div>
  </div>
  <div class="wrap hero-inner">
    <p class="brand-kicker reveal">5MB2 Digital</p>
    <h1 class="hero-title reveal">SEO, которое приводит клиентов</h1>
    <p class="hero-lead reveal">Продвижение сайтов по России: аудит, семантика, контент и техническая оптимизация — с понятными метриками роста.</p>
    <div class="hero-cta reveal">
      <a class="btn btn-primary btn-lg" href="#contact">Получить стратегию</a>
      <a class="btn btn-ghost btn-lg" href="#services">Смотреть услуги</a>
    </div>
  </div>
</section>

<section class="section" id="services">
  <div class="wrap">
    <header class="section-head reveal">
      <h2>Услуги SEO</h2>
      <p>Всё, что нужно агентству и бизнесу для органического роста — без лишнего шума.</p>
    </header>
    <div class="cards-3">
      <article class="card reveal">
        <span class="card-icon">01</span>
        <h3>SEO-аудит</h3>
        <p>Техника, индекс, скорость, ошибки, конкуренты. Список правок с приоритетами.</p>
      </article>
      <article class="card reveal">
        <span class="card-icon">02</span>
        <h3>Продвижение</h3>
        <p>Семантика, структура, контент, ссылки. Ежемесячная работа на видимость и трафик.</p>
      </article>
      <article class="card reveal">
        <span class="card-icon">03</span>
        <h3>Local SEO</h3>
        <p>Карты, региональная выдача, карточки компаний — заявки из вашего города.</p>
      </article>
      <article class="card reveal">
        <span class="card-icon">04</span>
        <h3>Техническое SEO</h3>
        <p>Core Web Vitals, индексация, разметка, миграции без просадки.</p>
      </article>
      <article class="card reveal">
        <span class="card-icon">05</span>
        <h3>Контент</h3>
        <p>Тексты под спрос и E-E-A-T: страницы услуг, статьи, коммерческие блоки.</p>
      </article>
      <article class="card reveal">
        <span class="card-icon">06</span>
        <h3>Отчётность</h3>
        <p>Позиции, трафик, заявки — в личном кабинете, без «магии в презентациях».</p>
      </article>
    </div>
  </div>
</section>

<section class="section section-alt" id="process">
  <div class="wrap">
    <header class="section-head reveal">
      <h2>Как работаем</h2>
      <p>Прозрачный процесс — от диагностики до стабильного роста.</p>
    </header>
    <ol class="steps">
      <li class="reveal"><strong>Диагностика</strong><span>Аудит и цели бизнеса</span></li>
      <li class="reveal"><strong>Стратегия</strong><span>Семантика и план на 90 дней</span></li>
      <li class="reveal"><strong>Реализация</strong><span>Техника, контент, ссылки</span></li>
      <li class="reveal"><strong>Рост</strong><span>Отчёты и масштабирование</span></li>
    </ol>
  </div>
</section>

<section class="section" id="cases">
  <div class="wrap">
    <header class="section-head reveal">
      <h2>Результаты</h2>
      <p>Ориентиры, а не обещания «топ-1 за неделю».</p>
    </header>
    <div class="stats">
      <div class="stat reveal"><strong data-count="180">0</strong><span>% средний рост органики*</span></div>
      <div class="stat reveal"><strong data-count="90">0</strong><span>дней до первых сдвигов</span></div>
      <div class="stat reveal"><strong data-count="24">0</strong><span>проекта в работе / год</span></div>
    </div>
    <p class="muted tiny reveal">*Ориентир по проектам с выполненными рекомендациями. Точные кейсы согласуем индивидуально.</p>
  </div>
</section>

<section class="section section-alt" id="faq">
  <div class="wrap narrow">
    <header class="section-head reveal">
      <h2>Частые вопросы</h2>
    </header>
    <div class="faq">
      <details class="reveal" open>
        <summary>Сколько ждать результат?</summary>
        <p>Первые движения — обычно 1–3 месяца. Устойчивый рост — от 3–6 месяцев при регулярной работе.</p>
      </details>
      <details class="reveal">
        <summary>Чем SEO отличается от рекламы?</summary>
        <p>Реклама даёт трафик сразу, пока есть бюджет. SEO накапливает актив: страницы продолжают приносить заявки.</p>
      </details>
      <details class="reveal">
        <summary>Нужен ли доступ к сайту?</summary>
        <p>Да — к админке / хостингу или через вашего разработчика. В кабинете видно статус задач.</p>
      </details>
      <details class="reveal">
        <summary>Как понять стоимость?</summary>
        <p>Зависит от ниши и конкуренции. Оставьте заявку — пришлём оценку и план без обязательств.</p>
      </details>
    </div>
  </div>
</section>

<section class="section cta-band" id="contact">
  <div class="wrap cta-inner reveal">
    <div>
      <h2>Обсудим ваш рост</h2>
      <p>Расскажите про сайт и нишу — вернёмся со стратегией и следующими шагами.</p>
    </div>
    <div class="cta-actions">
      <a class="btn btn-primary btn-lg" href="<?php echo esc_url(home_url('/cabinet/')); ?>">Личный кабинет</a>
      <?php
      // CF7 shortcode if form exists — fallback mailto
      if (shortcode_exists('contact-form-7')) {
          echo do_shortcode('[contact-form-7 title="Контактная форма 1"]');
      }
      ?>
      <a class="btn btn-ghost" href="mailto:hello@5mb2.ru?subject=SEO%20заявка">hello@5mb2.ru</a>
    </div>
  </div>
</section>

<?php get_footer(); ?>
