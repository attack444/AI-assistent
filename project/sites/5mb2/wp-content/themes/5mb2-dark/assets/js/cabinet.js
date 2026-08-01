(function () {
  "use strict";
  var form = document.getElementById("mb2-site-form");
  if (!form) return;
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var ok = form.querySelector(".auth-ok");
    var fd = new FormData(form);
    fd.append("action", "mb2_save_site");
    fd.append("nonce", (window.MB2 && MB2.nonce) || "");
    fetch((window.MB2 && MB2.ajax) || "/wp-admin/admin-ajax.php", {
      method: "POST",
      body: fd,
      credentials: "same-origin",
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (ok) {
          ok.hidden = !(res && res.success);
          ok.textContent = res && res.success ? "Сохранено" : ((res && res.data && res.data.message) || "Ошибка");
        }
      });
  });
})();
