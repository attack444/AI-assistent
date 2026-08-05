// =============================================================================
//  Контент студии игр 5mb2: анонсы/релизы/патчи/новости из БД (CMS-подход).
//  Добавить новый релиз = новая строка в game_releases (через админку/worker),
//  код при этом не меняется.
// =============================================================================
const prisma = require("../db");

// Лента для главной: свежие опубликованные записи.
async function feed(limit = 20) {
  return prisma.gameRelease.findMany({
    where: { published: true },
    orderBy: { publishedAt: "desc" },
    take: limit,
  });
}

// Записи одного типа (releases / patch / news) — для отдельных страниц.
async function byKind(kind, limit = 50) {
  return prisma.gameRelease.findMany({
    where: { published: true, kind },
    orderBy: { publishedAt: "desc" },
    take: limit,
  });
}

// Всё по конкретной игре (для страницы игры).
async function byGame(slug) {
  return prisma.gameRelease.findMany({
    where: { published: true, gameSlug: slug },
    orderBy: { publishedAt: "desc" },
  });
}

module.exports = { feed, byKind, byGame };
