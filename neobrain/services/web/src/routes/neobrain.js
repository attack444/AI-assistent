// =============================================================================
//  Роуты SaaS neobrain: страницы (SSR через EJS) + REST API.
//  Страницы:  /neobrain, /neobrain/plans, /neobrain/login, /neobrain/cabinet,
//             /neobrain/projects, /neobrain/chat, /neobrain/seo, /neobrain/reports
//  API:       /api/auth/*, /api/projects, /api/agent/*, /api/seo/*
// =============================================================================
const express = require("express");
const router = express.Router();

const authC = require("../controllers/authController");
const agentC = require("../controllers/agentController");
const seoC = require("../controllers/seoController");
const projC = require("../controllers/projectController");

const { requireAuth } = require("../middleware/auth");
const { validate } = require("../middleware/validate");
const { csrfCheck } = require("../middleware/security");
const { rateLimit, enforceQuota, requireBalance } = require("../middleware/quota");

// --- Публичные страницы -----------------------------------------------------
router.get("/", (req, res) => res.render("neobrain/home", { title: "NeoBrain — AI для деплоя и SEO", user: req.user }));
router.get("/plans", (req, res) => res.render("neobrain/plans", { title: "Тарифы", user: req.user }));
router.get("/login", (req, res) => res.render("neobrain/login", { title: "Вход", user: req.user }));

// --- Страницы личного кабинета (нужен вход) ---------------------------------
router.get("/cabinet", requireAuth, (req, res) => res.render("neobrain/cabinet", { title: "Кабинет", user: req.user }));
router.get("/projects", requireAuth, (req, res) => res.render("neobrain/projects", { title: "Проекты", user: req.user }));
router.get("/chat", requireAuth, (req, res) => res.render("neobrain/chat", { title: "Чат с агентом", user: req.user }));
router.get("/seo", requireAuth, (req, res) => res.render("neobrain/seo", { title: "SEO-аудит", user: req.user }));
router.get("/reports", requireAuth, (req, res) => res.render("neobrain/reports", { title: "Отчёты", user: req.user }));

// =============================================================================
//  REST API. Всё изменяющее — под CSRF-проверкой и rate-limit.
// =============================================================================
const api = express.Router();
api.use(csrfCheck); // защита от CSRF на всех POST/PUT/DELETE

// --- Авторизация ---
api.post("/auth/register", rateLimit({ windowSec: 60, max: 10 }), validate(authC.registerSchema), authC.register);
api.post("/auth/login", rateLimit({ windowSec: 60, max: 10 }), validate(authC.loginSchema), authC.login);
api.post("/auth/logout", authC.logout);
api.get("/auth/me", authC.me);

// --- Проекты ---
api.get("/projects", requireAuth, projC.list);
api.post("/projects", requireAuth, validate(projC.createSchema), projC.create);
api.post("/projects/:id/deploy", requireAuth, enforceQuota(), projC.deploy);

// --- AI-агент (diff-режим) ---
// quote — бесплатная смета; run — платно (нужен баланс) + rate-limit.
api.post("/agent/quote", requireAuth, agentC.quote);
api.post(
  "/agent/run",
  requireAuth,
  requireBalance(0.01),
  rateLimit({ windowSec: 60, max: 20 }),
  validate(agentC.runSchema),
  agentC.run
);

// --- SEO ---
api.post("/seo/audit", requireAuth, enforceQuota(), validate(seoC.auditSchema), seoC.enqueueAudit);
api.post("/seo/meta", requireAuth, requireBalance(0.01), validate(seoC.metaSchema), seoC.generateMeta);
api.get("/tasks/:queue/:id", requireAuth, seoC.taskStatus);

module.exports = { pages: router, api };
