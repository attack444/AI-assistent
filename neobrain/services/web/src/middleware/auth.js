// =============================================================================
//  ЕДИНАЯ АВТОРИЗАЦИЯ (SSO) между neobrain и 5mb2.
//
//  Как работает:
//   - при логине создаём случайный token, кладём данные сессии в Redis (TTL),
//     дублируем «якорь» сессии в БД (для аудита/отзыва);
//   - token отдаём в куки с Domain = COOKIE_DOMAIN (напр. ".neobrain.local").
//     Такая куки видна на ВСЕХ поддоменах/сайтах этого домена → залогинился
//     на одном сайте — залогинен на другом.
//   - на каждом запросе loadUser читает куки, достаёт сессию из Redis и
//     прикрепляет req.user.
// =============================================================================
const crypto = require("crypto");
const redis = require("../redis");
const prisma = require("../db");
const config = require("../config");

const COOKIE = "nb_sess";
const rkey = (token) => `sess:${token}`;

// Создать сессию и поставить куки (вызывается после успешного логина).
async function createSession(user, req, res) {
  const token = crypto.randomBytes(32).toString("hex");
  const expiresAt = new Date(Date.now() + config.sessionTtlSec * 1000);

  // Данные сессии в Redis — быстрый доступ на каждом запросе.
  await redis.set(
    rkey(token),
    JSON.stringify({ userId: user.id, role: user.role }),
    "EX",
    config.sessionTtlSec
  );

  // Якорь в БД — чтобы админ видел активные сессии и мог отозвать.
  await prisma.session.create({
    data: {
      userId: user.id,
      token,
      userAgent: req.headers["user-agent"]?.slice(0, 250),
      ip: req.ip,
      expiresAt,
    },
  });

  res.cookie(COOKIE, token, {
    httpOnly: true, // недоступна из JS → защита от XSS-кражи сессии
    sameSite: "lax", // разумный баланс между безопасностью и SSO
    secure: config.nodeEnv === "production", // только https в проде
    domain: config.cookieDomain, // ключ к SSO между доменами
    maxAge: config.sessionTtlSec * 1000,
    path: "/",
  });
  return token;
}

// Удалить сессию (логаут): чистим Redis, БД и куки.
async function destroySession(req, res) {
  const token = req.cookies?.[COOKIE];
  if (token) {
    await redis.del(rkey(token));
    await prisma.session.deleteMany({ where: { token } });
  }
  res.clearCookie(COOKIE, { domain: config.cookieDomain, path: "/" });
}

// Middleware: подгрузить пользователя из сессии (или оставить гостя).
async function loadUser(req, _res, next) {
  req.user = null;
  const token = req.cookies?.[COOKIE];
  if (!token) return next();
  try {
    const raw = await redis.get(rkey(token));
    if (!raw) return next(); // сессия истекла
    const { userId } = JSON.parse(raw);
    const user = await prisma.user.findUnique({ where: { id: userId } });
    if (user) req.user = user;
  } catch (e) {
    console.error("[auth] loadUser:", e.message);
  }
  next();
}

// Требовать вход. Для API — 401, для страниц — редирект на /login.
function requireAuth(req, res, next) {
  if (req.user) return next();
  if (req.path.startsWith("/api/")) return res.status(401).json({ error: "нужен вход" });
  return res.redirect("/neobrain/login");
}

// Требовать роль (например, только admin к дашборду).
function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.user) return res.status(401).json({ error: "нужен вход" });
    if (!roles.includes(req.user.role)) return res.status(403).json({ error: "нет прав" });
    next();
  };
}

module.exports = { createSession, destroySession, loadUser, requireAuth, requireRole, COOKIE };
