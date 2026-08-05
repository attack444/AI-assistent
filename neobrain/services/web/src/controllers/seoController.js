// =============================================================================
//  SEO-инструмент: пакетный аудит URL (в фоне через worker) и генерация
//  мета-тегов (быстро, синхронно, на дешёвой модели).
// =============================================================================
const { z } = require("zod");
const { addJob, getJobStatus } = require("../queue");
const llm = require("../llm");
const cache = require("../middleware/cache");

const auditSchema = z.object({
  projectId: z.string().uuid().optional(),
  urls: z.array(z.string().url()).min(1, "нужен хотя бы один URL").max(200),
});

const metaSchema = z.object({
  url: z.string().url().optional(),
  text: z.string().min(10, "дай текст страницы"),
});

// Поставить пакетный аудит в очередь. API сразу возвращает task_id.
async function enqueueAudit(req, res) {
  const { projectId, urls } = req.body;
  const taskId = await addJob("seoAudit", {
    userId: req.user.id,
    projectId: projectId || null,
    urls,
  });
  // 202 Accepted: задача принята, результат будет позже.
  res.status(202).json({ ok: true, task_id: taskId, queue: "seo-audit" });
}

// Узнать статус фоновой задачи.
async function taskStatus(req, res) {
  const { queue, id } = req.params;
  const map = { "seo-audit": "seoAudit", "game-release": "gameRelease", deploy: "deploy" };
  const status = await getJobStatus(map[queue] || queue, id);
  res.json(status);
}

// Синхронная генерация мета-тегов (title/description) — рутина, дешёвая модель.
async function generateMeta(req, res) {
  try {
    const { text, url } = req.body;
    const messages = [
      { role: "system", content: "Ты SEO-специалист. Верни JSON {title, description} на русском, title до 60, description до 160 символов." },
      { role: "user", content: `URL: ${url || "-"}\nТекст страницы:\n${text.slice(0, 4000)}` },
    ];
    const key = cache.key("meta-gen", messages);
    const hit = await cache.get(key);
    if (hit) {
      await llm.logCached({ taskType: "meta-gen", model: hit.model, userId: req.user.id });
      return res.json({ ...hit, cached: true });
    }
    const r = await llm.complete({ taskType: "meta-gen", messages, userId: req.user.id });
    await cache.set(key, r);
    res.json({ ...r, cached: false });
  } catch (err) {
    res.status(502).json({ error: err.message });
  }
}

module.exports = { enqueueAudit, taskStatus, generateMeta, auditSchema, metaSchema };
