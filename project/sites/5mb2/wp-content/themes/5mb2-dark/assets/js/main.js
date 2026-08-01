(function () {
  "use strict";

  // Sticky header elevation
  var header = document.querySelector("[data-elevate]");
  if (header) {
    var onScroll = function () {
      header.classList.toggle("is-elevated", window.scrollY > 12);
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  // Mobile nav
  var toggle = document.querySelector("[data-nav-toggle]");
  if (toggle) {
    toggle.addEventListener("click", function () {
      document.body.classList.toggle("nav-open");
    });
  }

  // Reveal on scroll
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
      { threshold: 0.14 }
    );
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add("is-in"); });
  }

  // Count-up stats
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

  // Auth forms (cabinet)
  function postAuth(action, form) {
    var err = form.querySelector(".auth-error");
    if (err) { err.hidden = true; err.textContent = ""; }
    var fd = new FormData(form);
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
      if (btn) btn.disabled = true;
      postAuth(action, form)
        .then(function (res) {
          if (res && res.success && res.data && res.data.redirect) {
            window.location.href = res.data.redirect;
            return;
          }
          var msg = (res && res.data && res.data.message) || "Ошибка";
          var err = form.querySelector(".auth-error");
          if (err) { err.hidden = false; err.textContent = msg; }
        })
        .catch(function () {
          var err = form.querySelector(".auth-error");
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
      fd.append("action", "mb2_logout");
      fd.append("nonce", (window.MB2 && MB2.nonce) || "");
      fetch((window.MB2 && MB2.ajax) || "/wp-admin/admin-ajax.php", {
        method: "POST",
        body: fd,
        credentials: "same-origin",
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          window.location.href = (res && res.data && res.data.redirect) || "/";
        });
    });
  }
})();
