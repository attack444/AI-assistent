(function () {
  "use strict";

  function post(action, form) {
    var fd = form instanceof FormData ? form : new FormData(form);
    fd.append("action", action);
    fd.append("nonce", (window.MB2 && MB2.nonce) || "");
    return fetch((window.MB2 && MB2.ajax) || "/wp-admin/admin-ajax.php", {
      method: "POST",
      body: fd,
      credentials: "same-origin",
    }).then(function (r) { return r.json(); });
  }

  document.querySelectorAll("[data-cab-tab]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var tab = btn.getAttribute("data-cab-tab");
      document.querySelectorAll("[data-cab-tab]").forEach(function (b) {
        b.classList.toggle("is-active", b === btn);
      });
      document.querySelectorAll("[data-cab-panel]").forEach(function (panel) {
        panel.hidden = panel.getAttribute("data-cab-panel") !== tab;
      });
    });
  });

  var profile = document.getElementById("mb2-profile-form");
  if (profile) {
    profile.addEventListener("submit", function (e) {
      e.preventDefault();
      var ok = profile.querySelector(".auth-ok");
      var err = profile.querySelector(".auth-error");
      if (ok) ok.hidden = true;
      if (err) err.hidden = true;
      post("mb2_save_profile", profile).then(function (res) {
        if (res && res.success) {
          if (ok) { ok.hidden = false; ok.textContent = "Сохранено"; }
        } else if (err) {
          err.hidden = false;
          err.textContent = (res && res.data && res.data.message) || "Ошибка";
        }
      });
    });
  }

  var req = document.getElementById("mb2-request-form");
  if (req) {
    req.addEventListener("submit", function (e) {
      e.preventDefault();
      var ok = req.querySelector(".auth-ok");
      var err = req.querySelector(".auth-error");
      if (ok) ok.hidden = true;
      if (err) err.hidden = true;
      post("mb2_save_request", req).then(function (res) {
        if (res && res.success) {
          if (ok) { ok.hidden = false; ok.textContent = "Заявка отправлена"; }
          req.reset();
        } else if (err) {
          err.hidden = false;
          err.textContent = (res && res.data && res.data.message) || "Ошибка";
        }
      });
    });
  }

  // legacy site-only form
  var form = document.getElementById("mb2-site-form");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var ok = form.querySelector(".auth-ok");
      post("mb2_save_site", form).then(function (res) {
        if (ok) {
          ok.hidden = !(res && res.success);
          ok.textContent = res && res.success ? "Сохранено" : "Ошибка";
        }
      });
    });
  }
})();
