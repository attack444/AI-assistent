// =============================================================================
//  ДАШБОРД АДМИНА: агрегация расходов токенов.
//  GET /api/admin/billing?from=YYYY-MM-DD&to=YYYY-MM-DD
//  Возвращает:
//    - byDay   : расходы по дням (для графика)
//    - byModel : расходы по моделям (какая модель сколько «съела»)
//    - byProject: топ проектов по расходам
//    - totals  : итоги (₽, число задач, доля из кэша)
// =============================================================================
const prisma = require("../db");

async function billing(req, res) {
  const to = req.query.to ? new Date(req.query.to) : new Date();
  const from = req.query.from
    ? new Date(req.query.from)
    : new Date(Date.now() - 30 * 24 * 60 * 60 * 1000); // по умолчанию 30 дней

  // Тянем логи за период и агрегируем в JS (просто и наглядно для обучения;
  // на больших объёмах вынести в SQL GROUP BY / materialized view).
  const logs = await prisma.tokenLog.findMany({
    where: { createdAt: { gte: from, lte: to } },
    include: { project: { select: { name: true } } },
  });

  const byDay = {};
  const byModel = {};
  const byProject = {};
  let totalRub = 0;
  let cachedCount = 0;

  for (const l of logs) {
    const day = l.createdAt.toISOString().slice(0, 10);
    const rub = Number(l.costRub);
    totalRub += rub;
    if (l.cached) cachedCount++;
    byDay[day] = (byDay[day] || 0) + rub;
    byModel[l.model] = (byModel[l.model] || 0) + rub;
    const pname = l.project?.name || "(без проекта)";
    byProject[pname] = (byProject[pname] || 0) + rub;
  }

  // Преобразуем в отсортированные массивы (удобно для Chart.js).
  const toSortedArray = (obj) =>
    Object.entries(obj)
      .map(([k, v]) => ({ key: k, rub: Math.round(v * 100) / 100 }))
      .sort((a, b) => (a.key < b.key ? -1 : 1));

  res.json({
    from: from.toISOString().slice(0, 10),
    to: to.toISOString().slice(0, 10),
    totals: {
      totalRub: Math.round(totalRub * 100) / 100,
      tasks: logs.length,
      cached: cachedCount,
      cacheHitRate: logs.length ? Math.round((cachedCount / logs.length) * 100) : 0,
    },
    byDay: toSortedArray(byDay),
    byModel: toSortedArray(byModel).sort((a, b) => b.rub - a.rub),
    byProject: toSortedArray(byProject).sort((a, b) => b.rub - a.rub).slice(0, 10),
  });
}

module.exports = { billing };
