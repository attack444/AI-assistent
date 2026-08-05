// =============================================================================
//  Регистрация / вход / выход / профиль. Пароли хэшируем bcrypt.
//  После входа создаётся SSO-сессия (см. middleware/auth.js).
// =============================================================================
const bcrypt = require("bcryptjs");
const { z } = require("zod");
const prisma = require("../db");
const auth = require("../middleware/auth");

// Схемы валидации (zod) — понятные правила для входных данных.
const registerSchema = z.object({
  email: z.string().email(),
  password: z.string().min(6, "пароль минимум 6 символов"),
  displayName: z.string().min(1).max(80).optional(),
});
const loginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
});

async function register(req, res) {
  const { email, password, displayName } = req.body;
  const exists = await prisma.user.findUnique({ where: { email } });
  if (exists) return res.status(409).json({ error: "email уже занят" });

  const passwordHash = await bcrypt.hash(password, 10);
  // Новичок по умолчанию — client (может пользоваться SaaS). Роль admin
  // назначаем вручную/сидом. Дадим стартовый баланс на пробу.
  const user = await prisma.user.create({
    data: { email, passwordHash, displayName, role: "client", balanceRub: 100 },
  });
  await auth.createSession(user, req, res);
  res.json({ ok: true, user: publicUser(user) });
}

async function login(req, res) {
  const { email, password } = req.body;
  const user = await prisma.user.findUnique({ where: { email } });
  if (!user || !(await bcrypt.compare(password, user.passwordHash))) {
    return res.status(401).json({ error: "неверный email или пароль" });
  }
  await auth.createSession(user, req, res);
  res.json({ ok: true, user: publicUser(user) });
}

async function logout(req, res) {
  await auth.destroySession(req, res);
  res.json({ ok: true });
}

async function me(req, res) {
  if (!req.user) return res.status(401).json({ error: "нужен вход" });
  res.json({ user: publicUser(req.user) });
}

// Никогда не отдаём наружу хэш пароля.
function publicUser(u) {
  return {
    id: u.id,
    email: u.email,
    displayName: u.displayName,
    role: u.role,
    balanceRub: Number(u.balanceRub),
    monthlyQuota: u.monthlyQuota,
  };
}

module.exports = { register, login, logout, me, registerSchema, loginSchema, publicUser };
