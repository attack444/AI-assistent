// =============================================================================
//  Роуты студии игр 5mb2. Контент — из БД (каталог игр + релизы + страницы),
//  поэтому новые игры/патчи/новости добавляются из админ-CMS без правки кода.
//  Страницы: / (главная), /catalog, /game/:slug, /releases, /blog, /gallery,
//            /fun, /about
// =============================================================================
const express = require("express");
const router = express.Router();
const games = require("../controllers/gamesController");

// Главная: избранные игры + свежая лента.
router.get("/", async (req, res) => {
  const [featured, items] = await Promise.all([games.featured(3), games.feed(8)]);
  res.render("games/home", { title: "5MB2 GAMES — инди-студия", user: req.user, featured, items });
});

// Каталог всех игр.
router.get("/catalog", async (req, res) => {
  res.render("games/catalog", { title: "Каталог игр", user: req.user, games: await games.catalog() });
});

// Страница конкретной игры: карточка игры + её патчи/релизы.
router.get("/game/:slug", async (req, res) => {
  const { game, releases } = await games.gamePage(req.params.slug);
  if (!game) return res.status(404).render("games/404", { title: "Игра не найдена", user: req.user });
  res.render("games/game", { title: game.title, user: req.user, game, releases });
});

// Релизы и патчи.
router.get("/releases", async (req, res) => {
  const [releases, patches] = await Promise.all([games.byKind("release", 50), games.byKind("patch", 50)]);
  res.render("games/releases", { title: "Релизы и патчи", user: req.user, releases, patches });
});

// Блог/новости.
router.get("/blog", async (req, res) => {
  res.render("games/blog", { title: "Блог", user: req.user, news: await games.byKind("news", 50) });
});

// Галерея анимаций (CSS/JS-эффекты, без тяжёлых фреймворков).
router.get("/gallery", (req, res) => res.render("games/gallery", { title: "Галерея анимаций", user: req.user }));

// «Приколюшки» — мини-интерактив на чистом JS.
router.get("/fun", (req, res) => res.render("games/fun", { title: "Приколюшки", user: req.user }));

// «О студии» — статическая страница из БД (CMS). Если не заполнена — дефолт.
router.get("/about", async (req, res) => {
  const page = await games.staticPage("about");
  res.render("games/about", { title: page?.title || "О студии", user: req.user, page });
});

module.exports = router;
