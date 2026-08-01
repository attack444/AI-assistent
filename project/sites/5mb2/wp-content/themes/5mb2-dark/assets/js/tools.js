(function () {
  "use strict";

  function len(el) {
    return (el && el.value ? el.value : "").trim().length;
  }

  function setMeter(kind, n, min, max) {
    var meter = document.querySelector('[data-meter="' + kind + '"] span');
    var hint = document.getElementById(kind === "title" ? "title-hint" : "desc-hint");
    if (!meter || !hint) return;
    var pct = Math.min(100, Math.round((n / (max + 20)) * 100));
    meter.style.width = pct + "%";
    var state = "short";
    if (n >= min && n <= max) state = "ok";
    else if (n > max) state = "long";
    meter.parentElement.setAttribute("data-state", state);
    var label =
      n +
      " символов · идеал " +
      min +
      "–" +
      max +
      (state === "ok" ? " · ок" : state === "long" ? " · длинновато" : " · можно чуть длиннее");
    hint.textContent = label;
  }

  var title = document.getElementById("tool-title");
  var desc = document.getElementById("tool-desc");
  if (title) {
    title.addEventListener("input", function () {
      setMeter("title", len(title), 50, 60);
    });
  }
  if (desc) {
    desc.addEventListener("input", function () {
      setMeter("desc", len(desc), 140, 160);
    });
  }

  var utmBuild = document.getElementById("utm-build");
  if (utmBuild) {
    utmBuild.addEventListener("click", function () {
      var base = (document.getElementById("utm-url").value || "").trim();
      var out = document.getElementById("utm-out");
      if (!base) {
        out.value = "";
        return;
      }
      try {
        var u = new URL(base);
        var src = (document.getElementById("utm-source").value || "").trim();
        var med = (document.getElementById("utm-medium").value || "").trim();
        var camp = (document.getElementById("utm-campaign").value || "").trim();
        if (src) u.searchParams.set("utm_source", src);
        if (med) u.searchParams.set("utm_medium", med);
        if (camp) u.searchParams.set("utm_campaign", camp);
        out.value = u.toString();
      } catch (e) {
        out.value = "Проверьте URL (нужен https://…)";
      }
    });
  }

  var utmCopy = document.getElementById("utm-copy");
  if (utmCopy) {
    utmCopy.addEventListener("click", function () {
      var out = document.getElementById("utm-out");
      var ok = document.getElementById("utm-ok");
      if (!out || !out.value) return;
      navigator.clipboard.writeText(out.value).then(
        function () {
          if (ok) {
            ok.hidden = false;
            setTimeout(function () {
              ok.hidden = true;
            }, 1600);
          }
        },
        function () {
          out.select();
        }
      );
    });
  }

  var bases = {
    audit: { low: 29000, mid: 39000, high: 55000, unit: "разово" },
    local: { low: 40000, mid: 55000, high: 80000, unit: "₽/мес" },
    growth: { low: 55000, mid: 85000, high: 140000, unit: "₽/мес" },
    tech: { low: 35000, mid: 50000, high: 90000, unit: "разово" },
  };
  var sizeMul = { s: 1, m: 1.15, l: 1.4 };

  var budgetRun = document.getElementById("budget-run");
  if (budgetRun) {
    budgetRun.addEventListener("click", function () {
      var goal = document.getElementById("budget-goal").value;
      var comp = document.getElementById("budget-comp").value;
      var size = document.getElementById("budget-size").value;
      var row = bases[goal] || bases.growth;
      var from = Math.round(row[comp] * (sizeMul[size] || 1));
      var to = Math.round(from * 1.35);
      var box = document.getElementById("budget-result");
      var sum = document.getElementById("budget-sum");
      var note = document.getElementById("budget-note");
      box.hidden = false;
      sum.textContent =
        "от " +
        from.toLocaleString("ru-RU") +
        " до ~" +
        to.toLocaleString("ru-RU") +
        " " +
        row.unit;
      note.textContent =
        "Ориентир по рынку РФ и входным ценам 5MB2. Финальный бюджет — после короткого брифа по нише и состоянию сайта.";
    });
  }

  function addCheck(list, ok, text) {
    var li = document.createElement("li");
    li.className = ok ? "is-done" : "is-progress";
    li.innerHTML = '<span class="dot" aria-hidden="true"></span><span></span>';
    li.querySelector("span:last-child").textContent = text;
    list.appendChild(li);
  }

  var checkRun = document.getElementById("check-run");
  if (checkRun) {
    checkRun.addEventListener("click", function () {
      var raw = (document.getElementById("check-url").value || "").trim();
      var list = document.getElementById("check-list");
      var note = document.getElementById("check-note");
      list.innerHTML = "";
      list.hidden = false;
      note.hidden = false;
      var u;
      try {
        u = new URL(raw.indexOf("http") === 0 ? raw : "https://" + raw);
      } catch (e) {
        addCheck(list, false, "Некорректный URL");
        return;
      }
      addCheck(list, u.protocol === "https:", u.protocol === "https:" ? "HTTPS включён" : "Нет HTTPS — срочно исправить");
      addCheck(list, true, "Проверяю robots.txt и sitemap…");

      var origin = u.origin;
      Promise.allSettled([
        fetch(origin + "/robots.txt", { mode: "cors" }).then(function (r) {
          return { kind: "robots", ok: r.ok, status: r.status };
        }),
        fetch(origin + "/sitemap.xml", { mode: "cors" }).then(function (r) {
          return { kind: "sitemap", ok: r.ok, status: r.status };
        }),
      ]).then(function (results) {
        list.innerHTML = "";
        addCheck(list, u.protocol === "https:", u.protocol === "https:" ? "HTTPS включён" : "Нет HTTPS");
        results.forEach(function (res) {
          if (res.status !== "fulfilled") {
            addCheck(
              list,
              false,
              "Не удалось проверить с браузера (CORS/блокировка). Откройте URL вручную или закажите аудит."
            );
            return;
          }
          var d = res.value;
          if (d.kind === "robots") {
            addCheck(list, d.ok, d.ok ? "robots.txt отвечает (" + d.status + ")" : "robots.txt не найден (" + d.status + ")");
          } else {
            addCheck(list, d.ok, d.ok ? "sitemap.xml найден" : "sitemap.xml не найден по /sitemap.xml");
          }
        });
        addCheck(list, true, "Дальше: скорость, индекс, коммерческие факторы — в SEO-аудите");
      });
    });
  }
})();
