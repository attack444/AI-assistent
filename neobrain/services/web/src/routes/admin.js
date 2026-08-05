// =============================================================================
//  Админский дашборд расходов. Доступ только роли admin.
//  Страница: /neobrain/admin  (график Chart.js)
//  API:      /api/admin/billing (агрегации)
// =============================================================================
const express = require("express");
const router = express.Router();
const adminC = require("../controllers/adminController");
const { requireAuth, requireRole } = require("../middleware/auth");

// Страница дашборда (HTML). Данные подтягивает через API ниже.
router.get("/neobrain/admin", requireAuth, requireRole("admin"), (req, res) => {
  res.render("admin/billing", { title: "Дашборд расходов", user: req.user });
});

// API с агрегациями расходов.
router.get("/api/admin/billing", requireAuth, requireRole("admin"), adminC.billing);

module.exports = router;
