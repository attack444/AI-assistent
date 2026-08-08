// =============================================================================
//  Админ-зона (только роль admin):
//   - дашборд расходов (Chart.js);
//   - CMS студии: игры, релизы/патчи/новости, статические страницы —
//     всё добавляется без правки кода.
// =============================================================================
const express = require("express");
const router = express.Router();
const adminC = require("../controllers/adminController");
const cms = require("../controllers/cmsController");
const { requireAuth, requireRole } = require("../middleware/auth");
const { validate } = require("../middleware/validate");
const { csrfCheck } = require("../middleware/security");

const onlyAdmin = [requireAuth, requireRole("admin")];

// --- Страницы админки ---
router.get("/neobrain/admin", ...onlyAdmin, (req, res) =>
  res.render("admin/billing", { title: "Дашборд расходов", user: req.user })
);
router.get("/neobrain/admin/cms", ...onlyAdmin, (req, res) =>
  res.render("admin/cms", { title: "CMS студии", user: req.user })
);

// --- API дашборда ---
router.get("/api/admin/billing", ...onlyAdmin, adminC.billing);

// --- API CMS (изменяющее — под CSRF) ---
router.get("/api/admin/games", ...onlyAdmin, cms.listGames);
router.post("/api/admin/games", ...onlyAdmin, csrfCheck, validate(cms.gameSchema), cms.createGame);
router.post("/api/admin/games/:id", ...onlyAdmin, csrfCheck, validate(cms.gamePatchSchema), cms.updateGame);

router.get("/api/admin/releases", ...onlyAdmin, cms.listReleases);
router.post("/api/admin/releases", ...onlyAdmin, csrfCheck, validate(cms.releaseSchema), cms.createRelease);
router.post("/api/admin/releases/:id/publish", ...onlyAdmin, csrfCheck, cms.publishRelease);

router.post("/api/admin/pages", ...onlyAdmin, csrfCheck, validate(cms.pageSchema), cms.upsertPage);

module.exports = router;
