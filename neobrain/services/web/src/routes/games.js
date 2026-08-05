// =============================================================================
//  Роуты студии игр 5mb2. Контент тянется из БД (game_releases), поэтому
//  новые игры/патчи добавляются без правки кода.
//  Страницы: / (главная-анонсы), /game/:slug, /releases, /blog, /gallery, /fun
// =============================================================================
const express = require("express");
const router = express.Router();
const games = require("../controllers/gamesController");

// Главная: свежие анонсы/релизы.
router.get("/", async (req, res) => {
  const items = await games.feed(12);
  res.render("games/home", { title: "5mb2 — инди-игры", user: req.user, items });
});

// Страница конкретной игры: анонс + список её патчей.
router.get("/game/:slug", async (req, res) => {
  const items = await games.byGame(req.params.slug);
  if (items.length === 0) return res.status(404).render("games/404", { title: "Игра не найдена", user: req.user });
  res.render("games/game", { title: items[0].title, user: req.user, slug: req.params.slug, items });
});

// Релизы и патчи.
router.get("/releases", async (req, res) => {
  const releases = await games.byKind("release", 50);
  const patches = await games.byKind("patch", 50);
  res.render("games/releases", { title: "Релизы и патчи", user: req.user, releases, patches });
});

// Блог/новости.
router.get("/blog", async (req, res) => {
  const news = await games.byKind("news", 50);
  res.render("games/blog", { title: "Блог", user: req.user, news });
});

// Галерея анимаций (CSS/JS-эффекты, без тяжёлых фреймворков).
router.get("/gallery", (req, res) => res.render("games/gallery", { title: "Галерея анимаций", user: req.user }));

// «Приколюшки» — мини-интерактив на чистом JS.
router.get("/fun", (req, res) => res.render("games/fun", { title: "Приколюшки", user: req.user }));

module.exports = router;
