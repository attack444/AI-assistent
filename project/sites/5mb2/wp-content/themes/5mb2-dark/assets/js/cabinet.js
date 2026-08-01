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
    }).then(function (r) {
      return r.json();
    });
  }

  function showAuthTab(tab) {
    var login = document.getElementById("mb2-login");
    var reg = document.getElementById("mb2-register");
    document.querySelectorAll("[data-auth-tab]").forEach(function (btn) {
      var on = btn.getAttribute("data-auth-tab") === tab;
      btn.classList.toggle("is-active", on);
      if (btn.getAttribute("role") === "tab") {
        btn.setAttribute("aria-selected", on ? "true" : "false");
      }
    });
    if (login) login.hidden = tab !== "login";
    if (reg) reg.hidden = tab !== "register";
    try {
      var url = new URL(window.location.href);
      if (tab === "register") url.searchParams.set("reg", "1");
      else url.searchParams.delete("reg");
      window.history.replaceState({}, "", url.pathname + url.search);
    } catch (e) {}
  }

  document.querySelectorAll("[data-auth-tab]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      showAuthTab(btn.getAttribute("data-auth-tab") || "login");
    });
  });

  function openCabTab(tab) {
    if (!tab) return;
    document.querySelectorAll("[data-cab-tab]").forEach(function (b) {
      b.classList.toggle("is-active", b.getAttribute("data-cab-tab") === tab);
    });
    document.querySelectorAll("[data-cab-panel]").forEach(function (panel) {
      panel.hidden = panel.getAttribute("data-cab-panel") !== tab;
    });
    try {
      if (history.replaceState) {
        history.replaceState(null, "", "#" + tab);
      }
    } catch (e) {}
    var grid = document.querySelector(".cabinet-grid");
    if (grid) grid.scrollIntoView({ behavior: "smooth", block: "start" });
    var activeNav = document.querySelector('.cabinet-nav [data-cab-tab="' + tab + '"]');
    if (activeNav && activeNav.scrollIntoView) {
      activeNav.scrollIntoView({ behavior: "smooth", inline: "center", block: "nearest" });
    }
  }

  document.querySelectorAll("[data-cab-tab]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      openCabTab(btn.getAttribute("data-cab-tab"));
    });
  });

  var hash = (window.location.hash || "").replace(/^#/, "");
  if (hash && document.querySelector('[data-cab-panel="' + hash + '"]')) {
    openCabTab(hash);
  }

  var editSite = document.getElementById("mb2-edit-site-link");
  if (editSite) {
    editSite.addEventListener("click", function () {
      openCabTab("project");
    });
  }
  var gotoOverview = document.getElementById("mb2-goto-overview");
  if (gotoOverview) {
    gotoOverview.addEventListener("click", function () {
      window.location.href = (window.MB2 && MB2.home ? MB2.home : "/") + "cabinet/";
    });
  }

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
          if (ok) {
            ok.hidden = false;
            ok.textContent = "Сохранено";
          }
        } else if (err) {
          err.hidden = false;
          err.textContent = (res && res.data && res.data.message) || "Ошибка";
        }
      });
    });
  }

  var onboardProfile = document.getElementById("mb2-onboard-profile");
  if (onboardProfile) {
    onboardProfile.addEventListener("submit", function (e) {
      e.preventDefault();
      var ok = onboardProfile.querySelector(".auth-ok");
      var err = onboardProfile.querySelector(".auth-error");
      var btn = onboardProfile.querySelector('button[type="submit"]');
      if (ok) ok.hidden = true;
      if (err) err.hidden = true;
      if (btn) btn.disabled = true;
      post("mb2_onboard_profile", onboardProfile)
        .then(function (res) {
          if (res && res.success && res.data && res.data.redirect) {
            window.location.href = res.data.redirect;
            return;
          }
          if (err) {
            err.hidden = false;
            err.textContent = (res && res.data && res.data.message) || "Ошибка";
          }
        })
        .finally(function () {
          if (btn) btn.disabled = false;
        });
    });
  }

  var onboardReq = document.getElementById("mb2-onboard-request");
  if (onboardReq) {
    onboardReq.addEventListener("submit", function (e) {
      e.preventDefault();
      var ok = onboardReq.querySelector(".auth-ok");
      var err = onboardReq.querySelector(".auth-error");
      var btn = onboardReq.querySelector('button[type="submit"]');
      if (ok) ok.hidden = true;
      if (err) err.hidden = true;
      if (btn) btn.disabled = true;
      post("mb2_onboard_request", onboardReq)
        .then(function (res) {
          if (res && res.success && res.data && res.data.redirect) {
            window.location.href = res.data.redirect;
            return;
          }
          if (err) {
            err.hidden = false;
            err.textContent = (res && res.data && res.data.message) || "Ошибка";
          }
        })
        .finally(function () {
          if (btn) btn.disabled = false;
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
          if (ok) {
            ok.hidden = false;
            ok.textContent = "Заявка отправлена";
          }
          req.reset();
        } else if (err) {
          err.hidden = false;
          err.textContent = (res && res.data && res.data.message) || "Ошибка";
        }
      });
    });
  }
})();
