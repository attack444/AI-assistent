/*!
 * NeoBrain — embeddable public chat widget (guest OK)
 * Usage:
 *   <script src="https://neobrain.site/widget.js" defer></script>
 *   <script>NeoBrainChat.mount({ title: "NeoBrain", site: "5mb2" });</script>
 *   (alias: AIHelperChat)
 */
(function (global) {
  "use strict";

  var AUTH_KEY = "neobrain-user-token";

  function css() {
    return [
      "#aih-root{all:initial;font-family:DM Sans,system-ui,sans-serif;}",
      "#aih-fab{position:fixed;right:18px;bottom:18px;z-index:2147483000;border:0;border-radius:999px;",
      "padding:14px 18px;background:#1a6cff;color:#f4f7ff;font:600 14px DM Sans,system-ui,sans-serif;",
      "cursor:pointer;box-shadow:0 12px 30px rgba(26,108,255,.35);}",
      "#aih-panel{position:fixed;right:18px;bottom:72px;z-index:2147483000;width:min(380px,calc(100vw - 24px));",
      "height:min(520px,70vh);display:none;flex-direction:column;background:#0b1220;color:#e8eef8;",
      "border:1px solid rgba(26,108,255,.28);border-radius:16px;overflow:hidden;",
      "box-shadow:0 24px 60px rgba(0,0,0,.45),0 0 40px rgba(26,108,255,.12);}",
      "#aih-panel.open{display:flex;}",
      "#aih-head{padding:12px 14px;background:#101a2e;color:#e8eef8;font:700 14px Sora,DM Sans,sans-serif;}",
      "#aih-head small{display:block;font:500 11px DM Sans,sans-serif;opacity:.7;margin-top:2px;}",
      "#aih-body{flex:1;overflow:auto;padding:12px;display:flex;flex-direction:column;gap:8px;}",
      ".aih-b{max-width:90%;padding:10px 12px;border-radius:12px;font:500 13px/1.45 DM Sans,sans-serif;white-space:pre-wrap;}",
      ".aih-bot{background:#121c2e;border:1px solid rgba(26,108,255,.22);align-self:flex-start;}",
      ".aih-user{background:#1a6cff;color:#f4f7ff;align-self:flex-end;}",
      "#aih-form{display:flex;gap:8px;padding:10px;border-top:1px solid rgba(26,108,255,.18);background:#080e1a;}",
      "#aih-form input{flex:1;border:1px solid rgba(26,108,255,.28);border-radius:10px;padding:10px;font:inherit;background:#0b1220;color:#e8eef8;}",
      "#aih-form button{border:0;border-radius:10px;padding:10px 12px;background:#1a6cff;color:#f4f7ff;cursor:pointer;font:600 13px DM Sans,sans-serif;}",
      "#aih-chips{display:flex;flex-wrap:wrap;gap:6px;padding:8px 12px;background:#080e1a;}",
      "#aih-chips button{border:1px solid rgba(26,108,255,.3);background:#121c2e;border-radius:999px;padding:6px 10px;",
      "font:500 11px DM Sans,sans-serif;cursor:pointer;color:#e8eef8;}",
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
    var title = opts.title || "NeoBrain";
    var placeholder = opts.placeholder || "Ваш вопрос…";
    var apiBase = (opts.apiBase || "/api").replace(/\/$/, "");
    var site = opts.site || "";

    if (document.getElementById("aih-root")) return;

    var style = el("style");
    style.textContent = css();
    document.head.appendChild(style);

    var root = el("div", { id: "aih-root" });
    var fab = el("button", { id: "aih-fab", type: "button" }, "Чат");
    var panel = el("div", { id: "aih-panel" });
    var head = el("div", { id: "aih-head" }, title);
    head.appendChild(el("small", null, "NeoBrain · Ollama / DeepSeek"));
    var body = el("div", { id: "aih-body" });
    var chipsWrap = el("div", { id: "aih-chips" });
    var form = el("form", { id: "aih-form" });
    var input = el("input", { type: "text", placeholder: placeholder, autocomplete: "off" });
    var send = el("button", { type: "submit" }, "→");

    var chips = opts.chips || ["Что умеешь?", "Как задеплоить?", "Тарифы"];
    chips.forEach(function (c) {
      var b = el("button", { type: "button" }, c);
      b.addEventListener("click", function () {
        input.value = c;
        form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
      });
      chipsWrap.appendChild(b);
    });

    form.appendChild(input);
    form.appendChild(send);
    panel.appendChild(head);
    panel.appendChild(body);
    panel.appendChild(chipsWrap);
    panel.appendChild(form);
    root.appendChild(fab);
    root.appendChild(panel);
    document.body.appendChild(root);

    var history = [];
    var token = "";
    try { token = localStorage.getItem(AUTH_KEY) || ""; } catch (_) {}

    fab.addEventListener("click", function () {
      panel.classList.toggle("open");
      if (panel.classList.contains("open")) input.focus();
    });

    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      var text = (input.value || "").trim();
      if (!text) return;
      input.value = "";
      send.disabled = true;

      var user = el("div", { class: "aih-b aih-user" }, text);
      body.appendChild(user);
      var bot = el("div", { class: "aih-b aih-bot" }, "…");
      body.appendChild(bot);
      body.scrollTop = body.scrollHeight;
      history.push({ role: "user", content: text });

      try {
        var headers = { "Content-Type": "application/json" };
        if (token) headers.Authorization = "Bearer " + token;
        var res = await fetch(apiBase + "/public/chat/stream", {
          method: "POST",
          headers: headers,
          body: JSON.stringify({ message: text, history: history.slice(-12), site: site }),
        });
        if (!res.ok) {
          var err = await res.json().catch(function () { return {}; });
          throw new Error(err.error || ("HTTP " + res.status));
        }
        var reader = res.body.getReader();
        var decoder = new TextDecoder();
        var buf = "";
        var full = "";
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

  var api = { mount: mount };
  global.NeoBrainChat = api;
  global.AIHelperChat = api; // совместимость со старыми вставками
})(typeof window !== "undefined" ? window : this);
