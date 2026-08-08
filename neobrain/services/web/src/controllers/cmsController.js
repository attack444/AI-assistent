// =============================================================================
//  АДМИН-CMS студии: добавлять игры, релизы/патчи/новости и статические
//  страницы БЕЗ правки кода. Доступ — только роль admin (см. routes/admin.js).
//
//  Ключевая идея: публикация релиза идёт через очередь game-release (worker),
//  как и просили — API кладёт задачу и возвращает task_id, worker публикует.
// =============================================================================
const { z } = require("zod");
const prisma = require("../db");
const { addJob } = require("../queue");

// -------------------- Игры --------------------
const gameSchema = z.object({
  slug: z.string().min(1).max(60).regex(/^[a-z0-9-]+$/, "slug: только a-z, 0-9, дефис"),
  title: z.string().min(1).max(120),
  tagline: z.string().max(160).optional(),
  description: z.string().max(20000).optional(),
  coverUrl: z.string().url().optional(),
  playUrl: z.string().url().optional(),
  status: z.enum(["soon", "dev", "released"]).default("dev"),
  featured: z.boolean().optional(),
  accent: z.string().regex(/^#[0-9a-fA-F]{6}$/, "accent: hex-цвет #RRGGBB").optional(),
});

async function listGames(_req, res) {
  res.json({ games: await prisma.game.findMany({ orderBy: { createdAt: "desc" } }) });
}

async function createGame(req, res) {
  const data = req.body;
  const exists = await prisma.game.findUnique({ where: { slug: data.slug } });
  if (exists) return res.status(409).json({ error: "игра с таким slug уже есть" });
  const game = await prisma.game.create({ data });
  res.json({ ok: true, game });
}

// Быстрое переключение флагов (опубликовать/в избранное/статус).
const gamePatchSchema = z.object({
  featured: z.boolean().optional(),
  status: z.enum(["soon", "dev", "released"]).optional(),
});
async function updateGame(req, res) {
  const game = await prisma.game.update({ where: { id: req.params.id }, data: req.body }).catch(() => null);
  if (!game) return res.status(404).json({ error: "игра не найдена" });
  res.json({ ok: true, game });
}

// -------------------- Релизы / патчи / новости --------------------
const releaseSchema = z.object({
  gameSlug: z.string().min(1).max(60),
  kind: z.enum(["announce", "release", "patch", "news"]).default("news"),
  title: z.string().min(1).max(160),
  version: z.string().max(30).optional(),
  body: z.string().min(1).max(50000),
  coverUrl: z.string().url().optional(),
});

// Создаём ЧЕРНОВИК релиза (published=false).
async function createRelease(req, res) {
  const release = await prisma.gameRelease.create({ data: { ...req.body, published: false } });
  res.json({ ok: true, release });
}

// Публикуем релиз ЧЕРЕЗ ОЧЕРЕДЬ (worker сам выставит published + publishedAt).
async function publishRelease(req, res) {
  const rel = await prisma.gameRelease.findUnique({ where: { id: req.params.id } });
  if (!rel) return res.status(404).json({ error: "релиз не найден" });
  const taskId = await addJob("gameRelease", { releaseId: rel.id });
  res.status(202).json({ ok: true, task_id: taskId, queue: "game-release" });
}

async function listReleases(_req, res) {
  res.json({ releases: await prisma.gameRelease.findMany({ orderBy: { createdAt: "desc" }, take: 100 }) });
}

// -------------------- Статические страницы (CMS) --------------------
const pageSchema = z.object({
  site: z.enum(["games", "neobrain"]).default("games"),
  slug: z.string().min(1).max(80).regex(/^[a-z0-9/-]+$/),
  title: z.string().min(1).max(160),
  html: z.string().min(1).max(100000),
});
async function upsertPage(req, res) {
  const { site, slug, title, html } = req.body;
  const page = await prisma.pageStatic.upsert({
    where: { site_slug: { site, slug } },
    update: { title, html },
    create: { site, slug, title, html },
  });
  res.json({ ok: true, page });
}

module.exports = {
  listGames, createGame, updateGame,
  createRelease, publishRelease, listReleases,
  upsertPage,
  gameSchema, gamePatchSchema, releaseSchema, pageSchema,
};
