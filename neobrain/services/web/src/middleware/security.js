// =============================================================================
//  БЕЗОПАСНОСТЬ: заголовки (защита от XSS/clickjacking) и CSRF-токен.
//  Без внешних зависимостей — чтобы было понятно, что происходит.
// =============================================================================
const crypto = require("crypto");

// Базовые защитные заголовки (аналог helmet, но явно и прозрачно).
function securityHeaders(_req, res, next) {
  res.setHeader("X-Content-Type-Options", "nosniff");
  res.setHeader("X-Frame-Options", "DENY"); // нельзя встроить в iframe
  res.setHeader("Referrer-Policy", "strict-origin-when-cross-origin");
  // CSP: разрешаем свои скрипты/стили + Chart.js с CDN на дашборде.
  res.setHeader(
    "Content-Security-Policy",
    "default-src 'self'; img-src 'self' data: https:; " +
      "script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; " +
      "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " +
      "font-src 'self' https://fonts.gstatic.com"
  );
  next();
}

// --- CSRF: double-submit cookie ---------------------------------------------
// Выдаём токен в куки; форма/JS обязаны прислать его же в заголовке/поле.
function csrfIssue(req, res, next) {
  let token = req.cookies?.csrf;
  if (!token) {
    token = crypto.randomBytes(16).toString("hex");
    res.cookie("csrf", token, { sameSite: "lax", path: "/" });
  }
  res.locals.csrf = token; // доступно в шаблонах как <%= csrf %>
  req.csrfToken = token;
  next();
}

// Проверяем CSRF на «изменяющих» методах (POST/PUT/DELETE).
function csrfCheck(req, res, next) {
  if (["GET", "HEAD", "OPTIONS"].includes(req.method)) return next();
  const sent = req.headers["x-csrf-token"] || req.body?._csrf;
  if (!sent || sent !== req.cookies?.csrf) {
    return res.status(403).json({ error: "CSRF-токен неверный" });
  }
  next();
}

module.exports = { securityHeaders, csrfIssue, csrfCheck };
