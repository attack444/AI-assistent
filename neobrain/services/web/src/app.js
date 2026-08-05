// =============================================================================
//  Сборка Express-приложения: middleware + маршруты обоих сайтов.
//
//  Один web-контейнер обслуживает ДВА домена. Как выбираем сайт:
//    - по Host заголовку (neobrain.* → SaaS, 5mb2.* → игры) для «/»;
//    - плюс всегда доступны явные префиксы /neobrain и /games — удобно
//      тестировать локально без двух доменов.
// =============================================================================
const path = require("path");
const express = require("express");
const cookieParser = require("cookie-parser");

const config = require("./config");
const { loadUser } = require("./middleware/auth");
const { securityHeaders, csrfIssue } = require("./middleware/security");

const neobrain = require("./routes/neobrain");
const gamesRoutes = require("./routes/games");
const adminRoutes = require("./routes/admin");

const app = express();

// Шаблонизатор EJS (простой SSR, без тяжёлых фронт-фреймворков).
app.set("view engine", "ejs");
app.set("views", path.join(__dirname, "views"));

// Базовые middleware.
app.set("trust proxy", 1); // за nginx — доверяем X-Forwarded-* (правильный req.ip)
app.use(express.json({ limit: "1mb" }));
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());
app.use(securityHeaders);
app.use(csrfIssue);
app.use(loadUser); // прикрепляет req.user (SSO)

// Статика: у каждого сайта своя папка.
app.use("/static/neobrain", express.static(path.join(__dirname, "public/neobrain")));
app.use("/static/games", express.static(path.join(__dirname, "public/games")));
app.use("/static/admin", express.static(path.join(__dirname, "public/admin")));

// Проверка живости для healthcheck/nginx.
app.get("/health", (_req, res) => res.json({ ok: true, service: "web" }));

// --- Общий REST API и админка (одинаковы для обоих доменов) ------------------
app.use("/api", neobrain.api);
app.use("/", adminRoutes);

// --- Явные префиксы (всегда работают, удобно локально) ----------------------
app.use("/neobrain", neobrain.pages);
app.use("/games", gamesRoutes);

// --- Корень «/» отдаём по домену --------------------------------------------
function isGamesHost(req) {
  const host = (req.hostname || "").toLowerCase();
  return config.hostsGames.some((h) => host === h || host.endsWith("." + h));
}
app.get("/", (req, res, next) => {
  if (isGamesHost(req)) return gamesRoutes.handle(req, res, next); // 5mb2 → игры
  return neobrain.pages.handle(req, res, next); // иначе → SaaS
});

// 404 и обработчик ошибок.
app.use((req, res) => res.status(404).render("partials/404", { title: "404", user: req.user }));
app.use((err, _req, res, _next) => {
  console.error("[web] ошибка:", err);
  res.status(500).json({ error: "внутренняя ошибка сервера" });
});

module.exports = app;
