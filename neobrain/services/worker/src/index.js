// =============================================================================
//  WORKER: слушает очереди Redis и выполняет тяжёлые задачи в фоне.
//  API кладёт задачу и сразу отдаёт task_id; результат воркер пишет в
//  returnvalue задачи (его читает /api/tasks/:queue/:id) и/или в БД.
//
//  Очереди: seo-audit, game-release, deploy.
// =============================================================================
const { Worker } = require("bullmq");
const IORedis = require("ioredis");
const { PrismaClient } = require("@prisma/client");

const prisma = new PrismaClient();
const connection = new IORedis(process.env.REDIS_URL || "redis://127.0.0.1:6379", {
  maxRetriesPerRequest: null,
});
const GATEWAY = process.env.LLM_GATEWAY_URL || "http://127.0.0.1:8090";

// ---------------------------------------------------------------------------
//  1) SEO-АУДИТ: качаем каждый URL, вытаскиваем базовые SEO-сигналы.
//     (Парсинг регуляркой — упрощённо и наглядно; для прода взять cheerio.)
// ---------------------------------------------------------------------------
function extractSeo(html) {
  const pick = (re) => (html.match(re)?.[1] || "").trim().slice(0, 300);
  const title = pick(/<title[^>]*>([\s\S]*?)<\/title>/i);
  const description = pick(/<meta[^>]+name=["']description["'][^>]+content=["']([^"']*)["']/i);
  const h1 = pick(/<h1[^>]*>([\s\S]*?)<\/h1>/i);
  const issues = [];
  if (!title) issues.push("нет <title>");
  else if (title.length > 60) issues.push("title длиннее 60 символов");
  if (!description) issues.push("нет meta description");
  else if (description.length > 160) issues.push("description длиннее 160 символов");
  if (!h1) issues.push("нет <h1>");
  return { title, description, h1, issues };
}

async function auditOne(url) {
  const t0 = Date.now();
  try {
    const resp = await fetch(url, { redirect: "follow", signal: AbortSignal.timeout(15000) });
    const html = await resp.text();
    const seo = extractSeo(html);
    return { url, status: resp.status, ms: Date.now() - t0, ...seo, ok: resp.ok };
  } catch (err) {
    return { url, status: 0, ms: Date.now() - t0, ok: false, error: err.message, issues: ["страница недоступна"] };
  }
}

new Worker(
  "seo-audit",
  async (job) => {
    const { urls } = job.data;
    const results = [];
    for (let i = 0; i < urls.length; i++) {
      results.push(await auditOne(urls[i]));
      await job.updateProgress(Math.round(((i + 1) / urls.length) * 100)); // прогресс для UI
    }
    const summary = {
      total: results.length,
      withIssues: results.filter((r) => r.issues && r.issues.length).length,
    };
    return { summary, results }; // попадёт в returnvalue задачи
  },
  { connection, concurrency: 2 }
).on("failed", (job, err) => console.error("[seo-audit] упала", job?.id, err.message));

// ---------------------------------------------------------------------------
//  2) ПУБЛИКАЦИЯ РЕЛИЗА ИГРЫ: помечаем запись опубликованной.
//     (Здесь же можно инвалидировать кэш страниц, слать уведомления и т.п.)
// ---------------------------------------------------------------------------
new Worker(
  "game-release",
  async (job) => {
    const { releaseId } = job.data;
    const rel = await prisma.gameRelease.update({
      where: { id: releaseId },
      data: { published: true, publishedAt: new Date() },
    });
    return { published: true, id: rel.id, slug: rel.gameSlug, title: rel.title };
  },
  { connection, concurrency: 4 }
).on("failed", (job, err) => console.error("[game-release] упала", job?.id, err.message));

// ---------------------------------------------------------------------------
//  3) ДЕПЛОЙ ПРОЕКТА (демо-заглушка): в проде здесь git pull + docker build + up.
//     Показываем идею: тяжёлая операция идёт в фоне, статус — через task_id.
// ---------------------------------------------------------------------------
new Worker(
  "deploy",
  async (job) => {
    const { projectId, repoUrl } = job.data;
    const steps = ["git pull", "docker build", "docker compose up -d", "healthcheck"];
    for (let i = 0; i < steps.length; i++) {
      await new Promise((r) => setTimeout(r, 300)); // имитация работы
      await job.updateProgress(Math.round(((i + 1) / steps.length) * 100));
    }
    return { deployed: true, projectId, repoUrl, steps };
  },
  { connection, concurrency: 1 }
).on("failed", (job, err) => console.error("[deploy] упала", job?.id, err.message));

console.log("[worker] запущен, слушаю очереди: seo-audit, game-release, deploy");

// Вспомогательная функция обращения к LLM-gateway (если задаче нужна модель).
async function callGateway(taskType, messages) {
  const resp = await fetch(`${GATEWAY}/complete`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ taskType, messages }),
  });
  return resp.json();
}
module.exports = { extractSeo, auditOne, callGateway };
