/*!
 * AI Helper — embeddable public chat widget (guest OK)
 * Usage:
 *   <script src="/sites/ai/widget.js" defer></script>
 *   <script>AIHelperChat.mount({ title: "Помощник", site: "5mb2" });</script>
 */
(function (global) {
  "use strict";

  var AUTH_KEY = "aihelper-user-token";

  function css() {
    return [
      "#aih-root{all:initial;font-family:Manrope,system-ui,sans-serif;}",
      "#aih-fab{position:fixed;right:18px;bottom:18px;z-index:2147483000;border:0;border-radius:999px;",
      "padding:14px 18px;background:#0b7f6e;color:#fff;font:600 14px Manrope,system-ui,sans-serif;",
      "cursor:pointer;box-shadow:0 12px 30px rgba(11,127,110,.35);}",
      "#aih-panel{position:fixed;right:18px;bottom:72px;z-index:2147483000;width:min(380px,calc(100vw - 24px));",
      "height:min(520px,70vh);display:none;flex-direction:column;background:#f4faf8;color:#0c1a16;",
      "border:1px solid rgba(12,26,22,.12);border-radius:16px;overflow:hidden;",
      "box-shadow:0 24px 60px rgba(12,26,22,.18);}",
      "#aih-panel.open{display:flex;}",
      "#aih-head{padding:12px 14px;background:#0f1714;color:#e7f0ea;font:700 14px Unbounded,Manrope,sans-serif;}",
      "#aih-head small{display:block;font:500 11px Manrope,sans-serif;opacity:.7;margin-top:2px;}",
      "#aih-body{flex:1;overflow:auto;padding:12px;display:flex;flex-direction:column;gap:8px;}",
      ".aih-b{max-width:90%;padding:10px 12px;border-radius:12px;font:500 13px/1.45 Manrope,sans-serif;white-space:pre-wrap;}",
      ".aih-bot{background:#fff;border:1px solid rgba(12,26,22,.1);align-self:flex-start;}",
      ".aih-user{background:#0b7f6e;color:#fff;align-self:flex-end;}",
      "#aih-form{display:flex;gap:8px;padding:10px;border-top:1px solid rgba(12,26,22,.1);background:#fff;}",
      "#aih-form input{flex:1;border:1px solid rgba(12,26,22,.15);border-radius:10px;padding:10px;font:inherit;}",
      "#aih-form button{border:0;border-radius:10px;padding:10px 12px;background:#0c1a16;color:#fff;cursor:pointer;font:600 13px Manrope,sans-serif;}",
      "#aih-chips{display:flex;flex-wrap:wrap;gap:6px;padding:8px 12px;background:#eef5f2;}",
      "#aih-chips button{border:1px solid rgba(12,26,22,.12);background:#fff;border-radius:999px;padding:6px 10px;",
      "font:500 11px Manrope,sans-serif;cursor:pointer;color:#0c1a16;}",
    ].join("");
  }

  function el(tag, attrs, text) {
    var n = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    if (text != null) n.textContent = text;
    return n;
  }

  function mount(opts) {
    opts = opts || {};
    var title = opts.title || "Помощник";
    var placeholder = opts.placeholder || "Ваш вопрос…";
    var apiBase = (opts.apiBase || "/api").replace(/\/$/, "");
    var site = opts.site || "";
    var greeting = opts.greeting || "Здравствуйте! Чем помочь?";
    var chips = opts.chips || ["Доставка", "Как заказать?", "Контакты"];

    if (document.getElementById("aih-root")) return;

    var style = el("style");
    style.textContent = css();
    document.head.appendChild(style);

    var root = el("div", { id: "aih-root" });
    var fab = el("button", { id: "aih-fab", type: "button" }, opts.fabLabel || "Чат");
    var panel = el("div", { id: "aih-panel" });
    var head = el("div", { id: "aih-head" }, title);
    head.appendChild(el("small", null, "Ответы онлайн · без доступа к админке"));
    var chipRow = el("div", { id: "aih-chips" });
    chips.forEach(function (c) {
      var b = el("button", { type: "button" }, c);
      b.addEventListener("click", function () {
        input.value = c;
        input.focus();
      });
      chipRow.appendChild(b);
    });
    var body = el("div", { id: "aih-body" });
    body.appendChild(el("div", { class: "aih-b aih-bot" }, greeting));
    var form = el("form", { id: "aih-form" });
    var input = el("input", { type: "text", maxlength: "2000", placeholder: placeholder, autocomplete: "off" });
    var send = el("button", { type: "submit" }, "→");
    form.appendChild(input);
    form.appendChild(send);
    panel.appendChild(head);
    panel.appendChild(chipRow);
    panel.appendChild(body);
    panel.appendChild(form);
    root.appendChild(panel);
    root.appendChild(fab);
    document.body.appendChild(root);

    var history = [];
    fab.addEventListener("click", function () {
      panel.classList.toggle("open");
      if (panel.classList.contains("open")) input.focus();
    });

    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      var text = (input.value || "").trim();
      if (!text || send.disabled) return;
      input.value = "";
      body.appendChild(el("div", { class: "aih-b aih-user" }, text));
      history.push({ role: "user", content: text });
      var bot = el("div", { class: "aih-b aih-bot" }, "…");
      body.appendChild(bot);
      body.scrollTop = body.scrollHeight;
      send.disabled = true;
      var full = "";
      var token = localStorage.getItem(AUTH_KEY) || "";
      var headers = { "Content-Type": "application/json" };
      if (token) headers.Authorization = "Bearer " + token;
      try {
        var res = await fetch(apiBase + "/public/chat/stream", {
          method: "POST",
          headers: headers,
          body: JSON.stringify({
            message: text,
            history: history.slice(0, -1),
            source: "widget",
            site: site,
          }),
        });
        if (!res.ok || !res.body) {
          var err = await res.json().catch(function () { return {}; });
          throw new Error(err.error || ("HTTP " + res.status));
        }
        var reader = res.body.getReader();
        var decoder = new TextDecoder();
        var buf = "";
        while (true) {
          var chunk = await reader.read();
          if (chunk.done) break;
          buf += decoder.decode(chunk.value, { stream: true });
          var parts = buf.split("\n\n");
          buf = parts.pop() || "";
          for (var i = 0; i < parts.length; i++) {
            var line = parts[i].trim();
            if (line.indexOf("data:") !== 0) continue;
            try {
              var ev = JSON.parse(line.slice(5).trim());
            } catch (_) {
              continue;
            }
            if (ev.type === "text") {
              full += ev.content || "";
              bot.textContent = full;
              body.scrollTop = body.scrollHeight;
            } else if (ev.type === "error") {
              throw new Error(ev.content || "Ошибка");
            }
          }
        }
        if (!full) throw new Error("Пустой ответ — обнови API на сервере");
        history.push({ role: "assistant", content: full });
      } catch (err) {
        bot.textContent = (err && err.message) || "Ошибка";
      } finally {
        send.disabled = false;
        input.focus();
      }
    });
  }

  global.AIHelperChat = { mount: mount };
})(typeof window !== "undefined" ? window : this);
