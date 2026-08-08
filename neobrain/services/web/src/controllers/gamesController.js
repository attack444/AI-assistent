// =============================================================================
//  Контент студии игр 5mb2 (CMS-подход): игры, релизы/патчи/новости и
//  статические страницы берутся из БД. Добавить игру/релиз = запись в БД
//  через админ-CMS, код не меняется.
// =============================================================================
const prisma = require("../db");

// Каталог всех игр (для страницы /games/catalog и главной).
async function catalog() {
  return prisma.game.findMany({ orderBy: [{ featured: "desc" }, { createdAt: "desc" }] });
}

// Только избранные игры (для блока на главной).
async function featured(limit = 3) {
  return prisma.game.findMany({ where: { featured: true }, orderBy: { createdAt: "desc" }, take: limit });
}

// Лента свежих опубликованных записей (анонсы/релизы/патчи/новости).
async function feed(limit = 20) {
  return prisma.gameRelease.findMany({
    where: { published: true },
    orderBy: { publishedAt: "desc" },
    take: limit,
  });
}

// Записи одного типа (release / patch / news) — для отдельных страниц.
async function byKind(kind, limit = 50) {
  return prisma.gameRelease.findMany({
    where: { published: true, kind },
    orderBy: { publishedAt: "desc" },
    take: limit,
  });
}

// Страница игры: сама игра (из каталога) + её лента релизов/патчей.
async function gamePage(slug) {
  const [game, releases] = await Promise.all([
    prisma.game.findUnique({ where: { slug } }),
    prisma.gameRelease.findMany({
      where: { published: true, gameSlug: slug },
      orderBy: { publishedAt: "desc" },
    }),
  ]);
  return { game, releases };
}

// Статическая страница студии (напр. «О студии»): site='games'.
async function staticPage(slug) {
  return prisma.pageStatic.findUnique({ where: { site_slug: { site: "games", slug } } });
}

module.exports = { catalog, featured, feed, byKind, gamePage, staticPage };
