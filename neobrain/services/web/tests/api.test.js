// =============================================================================
//  Интеграционные тесты web-API (Jest + Supertest).
//  Требуют запущенные Postgres (тестовая БД) и Redis. Обращения к llm-gateway
//  замоканы (global.fetch), поэтому сам gateway для тестов не нужен.
//
//  Запуск:  npm test   (в services/web)
//  Переменные: TEST_DATABASE_URL (по умолчанию neobrain_test), REDIS_URL.
// =============================================================================
const { execSync } = require("child_process");
const path = require("path");

// --- Окружение ДО импорта приложения ---------------------------------------
process.env.NODE_ENV = "test";
process.env.SESSION_SECRET = "test-secret-32-characters-minimum-len";
process.env.DATABASE_URL =
  process.env.TEST_DATABASE_URL ||
  "postgresql://neobrain:neobrain@127.0.0.1:5432/neobrain_test?schema=public";
// Отдельная Redis-БД (индекс 15), чтобы тесты не трогали данные dev-приложения.
process.env.REDIS_URL = process.env.TEST_REDIS_URL || "redis://127.0.0.1:6379/15";
process.env.LLM_GATEWAY_URL = "http://gateway.mock"; // всё равно замокаем fetch

const bcrypt = require("bcryptjs");
const request = require("supertest");
const prisma = require("../src/db");
const redis = require("../src/redis");
const app = require("../src/app");

// Достаём CSRF-токен: делаем GET, читаем куку csrf из ответа.
async function getCsrf(agent) {
  const res = await agent.get("/neobrain/login");
  const cookies = res.headers["set-cookie"] || [];
  const csrf = cookies
    .map((c) => c.split(";")[0])
    .find((c) => c.startsWith("csrf="));
  return csrf ? csrf.split("=")[1] : "";
}

beforeAll(() => {
  // Применяем миграции к тестовой БД.
  execSync("npx prisma migrate deploy --schema=../../prisma/schema.prisma", {
    cwd: path.join(__dirname, ".."),
    env: process.env,
    stdio: "ignore",
  });
});

beforeEach(async () => {
  // Чистим таблицы в FK-безопасном порядке.
  await prisma.tokenLog.deleteMany();
  await prisma.session.deleteMany();
  await prisma.project.deleteMany();
  await prisma.gameRelease.deleteMany();
  await prisma.game.deleteMany();
  await prisma.pageStatic.deleteMany();
  await prisma.user.deleteMany();
  // Чистим тестовую Redis-БД (кэш ответов, сессии, rate-limit) — только db 15.
  await redis.flushdb();
});

afterAll(async () => {
  await prisma.$disconnect();
  await redis.quit();
});

async function makeAdmin() {
  return prisma.user.create({
    data: {
      email: "admin@test.local",
      passwordHash: await bcrypt.hash("password123", 10),
      role: "admin",
      balanceRub: 1000,
    },
  });
}

// --- Тесты ------------------------------------------------------------------
describe("Авторизация и SSO-сессия", () => {
  test("регистрация создаёт клиента и сессию", async () => {
    const agent = request.agent(app);
    const csrf = await getCsrf(agent);
    const res = await agent
      .post("/api/auth/register")
      .set("X-CSRF-Token", csrf)
      .send({ email: "c@test.local", password: "password123", displayName: "C" });
    expect(res.status).toBe(200);
    expect(res.body.user.role).toBe("client");

    const me = await agent.get("/api/auth/me");
    expect(me.status).toBe(200);
    expect(me.body.user.email).toBe("c@test.local");
  });

  test("без CSRF-токена изменяющий запрос отклоняется (403)", async () => {
    const agent = request.agent(app);
    await getCsrf(agent); // кука есть, но заголовок не пошлём
    const res = await agent
      .post("/api/auth/register")
      .send({ email: "x@test.local", password: "password123" });
    expect(res.status).toBe(403);
  });

  test("невалидный email — 400 (zod)", async () => {
    const agent = request.agent(app);
    const csrf = await getCsrf(agent);
    const res = await agent
      .post("/api/auth/register")
      .set("X-CSRF-Token", csrf)
      .send({ email: "not-an-email", password: "password123" });
    expect(res.status).toBe(400);
  });
});

describe("Права доступа (RBAC)", () => {
  test("клиент НЕ имеет доступа к админ-биллингу (403)", async () => {
    const agent = request.agent(app);
    const csrf = await getCsrf(agent);
    await agent.post("/api/auth/register").set("X-CSRF-Token", csrf)
      .send({ email: "c2@test.local", password: "password123" });
    const res = await agent.get("/api/admin/billing");
    expect(res.status).toBe(403);
  });

  test("админ имеет доступ к биллингу (200)", async () => {
    await makeAdmin();
    const agent = request.agent(app);
    const csrf = await getCsrf(agent);
    await agent.post("/api/auth/login").set("X-CSRF-Token", csrf)
      .send({ email: "admin@test.local", password: "password123" });
    const res = await agent.get("/api/admin/billing");
    expect(res.status).toBe(200);
    expect(res.body.totals).toBeDefined();
  });
});

describe("AI-агент (gateway замокан)", () => {
  beforeEach(() => {
    // Мокаем обращения web → llm-gateway.
    jest.spyOn(global, "fetch").mockImplementation(async (url) => {
      if (String(url).endsWith("/quote")) {
        return { ok: true, status: 200, json: async () => ({ model: "mock", promptTokens: 10, costRub: 0.05 }) };
      }
      if (String(url).endsWith("/complete")) {
        return { ok: true, status: 200, json: async () => ({ ok: true, model: "mock", tier: "cheap", text: "ответ", promptTokens: 10, outputTokens: 5, costRub: 0.02 }) };
      }
      return { ok: false, status: 404, json: async () => ({}), text: async () => "" };
    });
  });
  afterEach(() => jest.restoreAllMocks());

  test("quote → цена, run → списывает баланс", async () => {
    const agent = request.agent(app);
    const csrf = await getCsrf(agent);
    await agent.post("/api/auth/register").set("X-CSRF-Token", csrf)
      .send({ email: "agent@test.local", password: "password123" });

    const quote = await agent.post("/api/agent/quote").set("X-CSRF-Token", csrf)
      .send({ instruction: "почини баг", diffText: "- a\n+ b" });
    expect(quote.status).toBe(200);
    expect(quote.body.costRub).toBeGreaterThan(0);

    const before = (await agent.get("/api/auth/me")).body.user.balanceRub;
    const run = await agent.post("/api/agent/run").set("X-CSRF-Token", csrf)
      .send({ instruction: "почини баг", diffText: "- a\n+ b" });
    expect(run.status).toBe(200);
    expect(run.body.text).toBe("ответ");
    const after = (await agent.get("/api/auth/me")).body.user.balanceRub;
    expect(after).toBeLessThan(before); // деньги списались
  });

  test("нет баланса → 402 на запуск агента", async () => {
    // создаём клиента и обнуляем баланс напрямую в БД
    const u = await prisma.user.create({
      data: { email: "poor@test.local", passwordHash: await bcrypt.hash("password123", 10), role: "client", balanceRub: 0 },
    });
    const agent = request.agent(app);
    const csrf = await getCsrf(agent);
    await agent.post("/api/auth/login").set("X-CSRF-Token", csrf)
      .send({ email: "poor@test.local", password: "password123" });
    const run = await agent.post("/api/agent/run").set("X-CSRF-Token", csrf)
      .send({ instruction: "x", diffText: "y" });
    expect(run.status).toBe(402);
    expect(u.balanceRub).toBeDefined();
  });
});

describe("CMS студии", () => {
  test("админ создаёт игру, она появляется в списке", async () => {
    await makeAdmin();
    const agent = request.agent(app);
    const csrf = await getCsrf(agent);
    await agent.post("/api/auth/login").set("X-CSRF-Token", csrf)
      .send({ email: "admin@test.local", password: "password123" });

    const create = await agent.post("/api/admin/games").set("X-CSRF-Token", csrf)
      .send({ slug: "test-game", title: "Test Game", status: "dev" });
    expect(create.status).toBe(200);

    const list = await agent.get("/api/admin/games");
    expect(list.body.games.some((g) => g.slug === "test-game")).toBe(true);
  });
});
