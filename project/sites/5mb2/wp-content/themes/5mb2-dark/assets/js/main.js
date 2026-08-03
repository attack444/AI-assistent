(function () {
  "use strict";

  var header = document.querySelector("[data-elevate]");
  if (header) {
    var onScroll = function () {
      header.classList.toggle("is-elevated", window.scrollY > 12);
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  var toggle = document.querySelector("[data-nav-toggle]");
  var panel = document.querySelector("[data-nav-panel]");
  var backdrop = document.querySelector(".nav-backdrop");

  function setNavOpen(open) {
    open = !!open;
    document.body.classList.toggle("nav-open", open);
    if (toggle) {
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      toggle.setAttribute("aria-label", open ? "Закрыть меню" : "Открыть меню");
    }
    if (panel) panel.setAttribute("aria-hidden", open ? "false" : "true");
    if (backdrop) backdrop.setAttribute("aria-hidden", open ? "false" : "true");
  }

  if (toggle && panel) {
    toggle.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      setNavOpen(!document.body.classList.contains("nav-open"));
    });
    document.querySelectorAll("[data-nav-close]").forEach(function (el) {
      el.addEventListener("click", function (e) {
        e.preventDefault();
        setNavOpen(false);
      });
    });
    panel.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        setNavOpen(false);
      });
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") setNavOpen(false);
    });
    window.addEventListener(
      "resize",
      function () {
        if (window.innerWidth > 960) setNavOpen(false);
      },
      { passive: true }
    );
  }

  var reveals = document.querySelectorAll(".reveal");
  if (reveals.length && "IntersectionObserver" in window) {
    var io = new IntersectionObserver(
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
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add("is-in"); });
  }

  var stats = document.querySelectorAll("[data-count]");
  if (stats.length && "IntersectionObserver" in window) {
    var cio = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (e) {
          if (!e.isIntersecting) return;
          var el = e.target;
          var target = parseInt(el.getAttribute("data-count"), 10) || 0;
          var start = performance.now();
          var dur = 1100;
          function tick(now) {
            var t = Math.min(1, (now - start) / dur);
            var eased = 1 - Math.pow(1 - t, 3);
            el.textContent = String(Math.round(target * eased));
            if (t < 1) requestAnimationFrame(tick);
          }
          requestAnimationFrame(tick);
          cio.unobserve(el);
        });
      },
      { threshold: 0.4 }
    );
    stats.forEach(function (el) { cio.observe(el); });
  }

  function postAjax(action, form) {
    var fd = form instanceof FormData ? form : new FormData(form);
    fd.append("action", action);
    fd.append("nonce", (window.MB2 && MB2.nonce) || "");
    return fetch((window.MB2 && MB2.ajax) || "/wp-admin/admin-ajax.php", {
      method: "POST",
      body: fd,
      credentials: "same-origin",
    }).then(function (r) { return r.json(); });
  }

  document.querySelectorAll("form[data-auth]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var kind = form.getAttribute("data-auth");
      var action = kind === "register" ? "mb2_register" : "mb2_login";
      var btn = form.querySelector('button[type="submit"]');
      var err = form.querySelector(".auth-error");
      if (err) { err.hidden = true; err.textContent = ""; }
      if (btn) btn.disabled = true;
      postAjax(action, form)
        .then(function (res) {
          if (res && res.success && res.data && res.data.redirect) {
            window.location.href = res.data.redirect;
            return;
          }
          var msg = (res && res.data && res.data.message) || "Ошибка";
          if (err) { err.hidden = false; err.textContent = msg; }
        })
        .catch(function () {
          if (err) { err.hidden = false; err.textContent = "Сеть недоступна"; }
        })
        .finally(function () {
          if (btn) btn.disabled = false;
        });
    });
  });

  var logout = document.getElementById("mb2-logout");
  if (logout) {
    logout.addEventListener("click", function () {
      var fd = new FormData();
      postAjax("mb2_logout", fd).then(function (res) {
        window.location.href = (res && res.data && res.data.redirect) || "/";
      });
    });
  }

  document.querySelectorAll("form[data-lead]").forEach(function (lead) {
    lead.addEventListener("submit", function (e) {
      e.preventDefault();
      var btn = lead.querySelector('button[type="submit"]');
      var note = lead.querySelector(".form-note");
      if (btn) btn.disabled = true;
      if (note) {
        note.hidden = true;
        note.classList.remove("is-error", "is-ok");
      }
      postAjax("mb2_lead", lead)
        .then(function (res) {
          if (res && res.success) {
            var url = (res.data && res.data.redirect) || (window.MB2 && MB2.thanks);
            if (url) {
              window.location.href = url;
              return;
            }
            if (note) {
              note.hidden = false;
              note.classList.add("is-ok");
              note.textContent = (res.data && res.data.message) || "Отправлено";
            }
            lead.reset();
            return;
          }
          if (note) {
            note.hidden = false;
            note.classList.add("is-error");
            note.textContent = (res && res.data && res.data.message) || "Не удалось отправить";
          }
        })
        .catch(function () {
          if (note) {
            note.hidden = false;
            note.classList.add("is-error");
            note.textContent = "Сеть недоступна";
          }
        })
        .finally(function () {
          if (btn) btn.disabled = false;
        });
    });
  });

  // Feedback section (#feedback) — idea / bug
  (function () {
    var form = document.getElementById("mb2-feedback-form");
    if (!form || typeof postAjax !== "function") return;
    var pageInput = form.querySelector("[data-feedback-page]");

    if (window.location.hash === "#feedback") {
      var sec = document.getElementById("feedback");
      if (sec) setTimeout(function () { sec.scrollIntoView({ behavior: "smooth", block: "start" }); }, 80);
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var btn = form.querySelector('button[type="submit"]');
      var note = form.querySelector(".form-note");
      if (btn) btn.disabled = true;
      if (note) {
        note.hidden = true;
        note.classList.remove("is-error", "is-ok");
      }
      if (pageInput) pageInput.value = window.location.href;
      postAjax("mb2_feedback", form)
        .then(function (res) {
          if (res && res.success) {
            if (note) {
              note.hidden = false;
              note.classList.add("is-ok");
              note.textContent = (res.data && res.data.message) || "Спасибо!";
            }
            form.reset();
            return;
          }
          if (note) {
            note.hidden = false;
            note.classList.add("is-error");
            note.textContent = (res && res.data && res.data.message) || "Не удалось отправить";
          }
        })
        .catch(function () {
          if (note) {
            note.hidden = false;
            note.classList.add("is-error");
            note.textContent = "Сеть недоступна";
          }
        })
        .finally(function () {
          if (btn) btn.disabled = false;
        });
    });
  })();

  // Carousel
  document.querySelectorAll("[data-carousel]").forEach(function (root) {
    var slides = Array.prototype.slice.call(root.querySelectorAll(".carousel-slide"));
    if (slides.length < 2) return;
    var dotsWrap = root.querySelector("[data-carousel-dots]");
    var i = 0;
    var timer;

    function go(n) {
      i = (n + slides.length) % slides.length;
      slides.forEach(function (s, idx) {
        s.classList.toggle("is-active", idx === i);
      });
      if (dotsWrap) {
        Array.prototype.forEach.call(dotsWrap.children, function (d, idx) {
          d.classList.toggle("is-active", idx === i);
        });
      }
    }

    if (dotsWrap) {
      slides.forEach(function (_, idx) {
        var b = document.createElement("button");
        b.type = "button";
        b.setAttribute("aria-label", "Слайд " + (idx + 1));
        if (idx === 0) b.classList.add("is-active");
        b.addEventListener("click", function () { go(idx); restart(); });
        dotsWrap.appendChild(b);
      });
    }

    var prev = root.querySelector("[data-carousel-prev]");
    var next = root.querySelector("[data-carousel-next]");
    if (prev) prev.addEventListener("click", function () { go(i - 1); restart(); });
    if (next) next.addEventListener("click", function () { go(i + 1); restart(); });

    // Swipe на телефоне
    var touchX = null;
    root.addEventListener(
      "touchstart",
      function (e) {
        if (!e.changedTouches || !e.changedTouches[0]) return;
        touchX = e.changedTouches[0].clientX;
      },
      { passive: true }
    );
    root.addEventListener(
      "touchend",
      function (e) {
        if (touchX == null || !e.changedTouches || !e.changedTouches[0]) return;
        var dx = e.changedTouches[0].clientX - touchX;
        touchX = null;
        if (Math.abs(dx) < 40) return;
        if (dx < 0) go(i + 1);
        else go(i - 1);
        restart();
      },
      { passive: true }
    );

    function restart() {
      clearInterval(timer);
      timer = setInterval(function () { go(i + 1); }, 5500);
    }
    restart();
  });

  // —— ЮKassa: фиксированный пакет услуги ——
  document.querySelectorAll("[data-mb2-pay]").forEach(function (box) {
    var btn = box.querySelector("[data-pay-submit]");
    var note = box.querySelector("[data-pay-note]");
    var input = box.querySelector('input[name="pay_email"]');
    if (input && window.MB2 && MB2.user && MB2.user.email) {
      input.value = MB2.user.email;
    }
    if (!btn) return;
    btn.addEventListener("click", function () {
      var email = (input && input.value ? input.value : "").trim();
      var pkg = box.getAttribute("data-package") || "";
      if (!email || email.indexOf("@") < 0) {
        if (note) {
          note.hidden = false;
          note.textContent = "Укажите email для чека и связи";
        }
        return;
      }
      if (!pkg) return;
      btn.disabled = true;
      if (note) {
        note.hidden = false;
        note.textContent = "Открываю оплату ЮKassa…";
      }
      var api = (window.MB2 && MB2.payApi) || "https://neobrain.site/api";
      var thanks = (window.MB2 && MB2.thanks) || (location.origin + "/spasibo/");
      var sep = thanks.indexOf("?") >= 0 ? "&" : "?";
      fetch(api + "/public/pay/package", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          email: email,
          package: pkg,
          return_url: thanks + sep + "paid=1&package=" + encodeURIComponent(pkg),
        }),
      })
        .then(function (r) {
          return r.json().then(function (d) {
            return { ok: r.ok, status: r.status, data: d || {} };
          });
        })
        .then(function (res) {
          if (res.data.confirmation_url) {
            location.href = res.data.confirmation_url;
            return;
          }
          var msg =
            res.data.error ||
            (res.status === 503
              ? "Оплата картой ещё подключается. Можно по реквизитам."
              : "Не удалось создать платёж");
          if (note) {
            note.hidden = false;
            note.textContent = msg;
          }
          btn.disabled = false;
        })
        .catch(function () {
          if (note) {
            note.hidden = false;
            note.textContent = "Сеть недоступна. Попробуйте позже или реквизиты.";
          }
          btn.disabled = false;
        });
    });
  });
})();
