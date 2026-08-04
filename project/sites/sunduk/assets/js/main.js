(function () {
  const script = document.currentScript;
  const ROOT = (script && script.getAttribute("data-root")) || ".";

  function url(path) {
    if (!path) return ROOT + "/";
    if (path.startsWith("http") || path.startsWith("#") || path.startsWith("mailto:")) return path;
    return ROOT.replace(/\/$/, "") + "/" + path.replace(/^\//, "");
  }

  function injectChrome() {
    const headerHost = document.getElementById("site-header");
    const footerHost = document.getElementById("site-footer");
    const page = document.body.getAttribute("data-page") || "";

    if (headerHost) {
      headerHost.innerHTML =
        '<div class="top"><div class="wrap top-inner">' +
        '<a class="brand" href="' + url("") + '">SUN<span>DUK</span></a>' +
        '<button class="nav-toggle" type="button" id="nav-toggle" aria-expanded="false">Меню</button>' +
        '<nav class="nav" id="site-nav" aria-label="Разделы">' +
        navLink("games/", "Игры", page) +
        navLink("play/", "Играть", page) +
        navLink("reviews/", "Обзоры", page) +
        navLink("news/", "Анонсы", page) +
        navLink("about/", "Студия", page) +
        navLink("contact/", "Контакт", page) +
        navLink("press/", "Пресса", page) +
        "</nav></div></div>";
    }

    if (footerHost) {
      footerHost.innerHTML =
        '<footer class="site-footer"><div class="wrap footer-grid">' +
        '<div><a class="brand" href="' + url("") + '">SUN<span>DUK</span></a>' +
        "<p>Мобильные игры, обзоры и анонсы. Студия Славы Сундукова.</p></div>" +
        "<div><h4>Игры</h4><ul>" +
        '<li><a href="' + url("games/") + '">Каталог</a></li>' +
        '<li><a href="' + url("games/chest-dash/") + '">Chest Dash</a></li>' +
        '<li><a href="' + url("play/") + '">Играть в браузере</a></li>' +
        "</ul></div>" +
        "<div><h4>Студия</h4><ul>" +
        '<li><a href="' + url("about/") + '">О нас</a></li>' +
        '<li><a href="' + url("news/") + '">Анонсы</a></li>' +
        '<li><a href="' + url("contact/") + '">hello@5mb2.ru</a></li>' +
        '<li><a href="' + url("press/") + '">Press kit</a></li>' +
        "</ul></div></div></footer>";
    }

    const toggle = document.getElementById("nav-toggle");
    const nav = document.getElementById("site-nav");
    if (toggle && nav) {
      toggle.addEventListener("click", function () {
        const open = nav.classList.toggle("is-open");
        toggle.setAttribute("aria-expanded", open ? "true" : "false");
      });
    }
  }

  function navLink(path, label, page) {
    const id = path.replace(/\/$/, "") || "home";
    const active = page === id || (page === "home" && path === "") ? " is-active" : "";
    return '<a class="' + active.trim() + '" href="' + url(path) + '">' + label + "</a>";
  }

  function reveal() {
    if (!("IntersectionObserver" in window)) {
      document.querySelectorAll(".reveal").forEach(function (el) {
        el.classList.add("is-in");
      });
      return;
    }
    const io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) {
            e.target.classList.add("is-in");
            io.unobserve(e.target);
          }
        });
      },
      { threshold: 0.12 }
    );
    document.querySelectorAll(".reveal").forEach(function (el) {
      io.observe(el);
    });
  }

  function platformLabel(p) {
    return { ios: "iOS", android: "Android", web: "Web" }[p] || p;
  }

  function renderGameTile(g) {
    const href = url(g.href);
    const badge = g.status === "play" ? "play" : "soon";
    return (
      '<a class="game-tile reveal" href="' +
      href +
      '">' +
      '<div class="game-cover" data-tone="' +
      (g.tone || "lime") +
      '" data-badge="' +
      badge +
      '"></div>' +
      "<div><h3>" +
      g.title +
      "</h3><p>" +
      g.blurb +
      "</p>" +
      '<div class="meta-row" style="margin-top:10px">' +
      g.platforms.map(platformLabel).join(" · ") +
      " · " +
      g.genres.join(" · ") +
      "</div></div></a>"
    );
  }

  function mountCatalog() {
    const grid = document.getElementById("games-catalog");
    if (!grid || !window.SUNDUK_GAMES) return;

    const q = document.getElementById("games-q");
    const platform = document.getElementById("games-platform");
    const status = document.getElementById("games-status");
    const chips = document.querySelectorAll("[data-genre]");
    let genre = "all";

    chips.forEach(function (chip) {
      chip.addEventListener("click", function () {
        chips.forEach(function (c) {
          c.classList.remove("is-on");
        });
        chip.classList.add("is-on");
        genre = chip.getAttribute("data-genre") || "all";
        paint();
      });
    });

    function paint() {
      const query = (q && q.value ? q.value : "").trim().toLowerCase();
      const plat = platform ? platform.value : "all";
      const st = status ? status.value : "all";

      const list = window.SUNDUK_GAMES.filter(function (g) {
        if (st !== "all" && g.status !== st) return false;
        if (plat !== "all" && g.platforms.indexOf(plat) < 0) return false;
        if (genre !== "all" && g.genres.indexOf(genre) < 0) return false;
        if (query) {
          const hay = (g.title + " " + g.blurb + " " + g.genres.join(" ")).toLowerCase();
          if (hay.indexOf(query) < 0) return false;
        }
        return true;
      });

      if (!list.length) {
        grid.innerHTML = '<div class="empty">Нет игр по фильтру — сбросьте поиск или жанр.</div>';
        return;
      }
      grid.innerHTML = list.map(renderGameTile).join("");
      reveal();
    }

    if (q) q.addEventListener("input", paint);
    if (platform) platform.addEventListener("change", paint);
    if (status) status.addEventListener("change", paint);
    paint();
  }

  function mountHomeGames() {
    const host = document.getElementById("home-games");
    if (!host || !window.SUNDUK_GAMES) return;
    host.innerHTML = window.SUNDUK_GAMES.slice(0, 3).map(renderGameTile).join("");
  }

  function mountList(id, items) {
    const host = document.getElementById(id);
    if (!host || !items) return;
    host.innerHTML = items
      .map(function (it) {
        return (
          '<a class="item reveal" href="' +
          url(it.href) +
          '">' +
          '<span class="when">' +
          it.dateLabel +
          "</span><div><h3>" +
          it.title +
          "</h3><p>" +
          it.blurb +
          "</p></div></a>"
        );
      })
      .join("");
  }

  function mountContactForm() {
    const form = document.getElementById("contact-form");
    if (!form) return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      const hp = form.querySelector('[name="website"]');
      if (hp && hp.value) return;
      const name = (form.querySelector('[name="name"]') || {}).value || "";
      const email = (form.querySelector('[name="email"]') || {}).value || "";
      const topic = (form.querySelector('[name="topic"]') || {}).value || "";
      const msg = (form.querySelector('[name="message"]') || {}).value || "";
      const subject = encodeURIComponent("SUNDUK [" + (topic || "other") + "]: " + (name || "сообщение"));
      const body = encodeURIComponent(
        (msg || "") + "\n\n— " + name + (email ? " <" + email + ">" : "") + (topic ? "\nТема: " + topic : "")
      );
      const note = document.getElementById("contact-note");
      if (note) {
        note.hidden = false;
        note.className = "form-ok";
        note.textContent = "Открываю почту… Если не открылась — напишите на hello@5mb2.ru";
      }
      window.location.href = "mailto:hello@5mb2.ru?subject=" + subject + "&body=" + body;
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    injectChrome();
    mountHomeGames();
    mountCatalog();
    mountList("home-reviews", window.SUNDUK_REVIEWS);
    mountList("home-news", window.SUNDUK_NEWS);
    mountList("reviews-list", window.SUNDUK_REVIEWS);
    mountList("news-list", window.SUNDUK_NEWS);
    mountContactForm();
    reveal();
  });

  window.SUNDUK = { root: ROOT, url: url, renderGameTile: renderGameTile };
})();
