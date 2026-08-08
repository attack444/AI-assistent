// =============================================================================
//  Наполнение БД демо-данными: админ, клиент, проекты, релизы игр, немного
//  логов расхода токенов (чтобы дашборд был не пустой).
//  ЗАПУСК:  cd services/web && npm run seed   (или node ../../scripts/seed.js)
// =============================================================================
const path = require("path");
// Используем @prisma/client, сгенерированный для web-сервиса.
const { PrismaClient } = require(path.join(__dirname, "../services/web/node_modules/@prisma/client"));
const bcrypt = require(path.join(__dirname, "../services/web/node_modules/bcryptjs"));

const prisma = new PrismaClient();

async function main() {
  const pass = await bcrypt.hash("password123", 10);

  // Админ (видит дашборд) и клиент (пользуется SaaS).
  const admin = await prisma.user.upsert({
    where: { email: "admin@neobrain.local" },
    update: {},
    create: { email: "admin@neobrain.local", passwordHash: pass, displayName: "Админ", role: "admin", balanceRub: 1000 },
  });
  const client = await prisma.user.upsert({
    where: { email: "client@neobrain.local" },
    update: {},
    create: { email: "client@neobrain.local", passwordHash: pass, displayName: "Клиент", role: "client", balanceRub: 500 },
  });

  // Проекты клиента.
  const p1 = await prisma.project.create({ data: { ownerId: client.id, name: "Лендинг курса", repoUrl: "https://github.com/example/landing", domain: "course.example" } });
  const p2 = await prisma.project.create({ data: { ownerId: client.id, name: "Магазин", repoUrl: "https://github.com/example/shop", domain: "shop.example" } });

  // --- Каталог игр студии 5MB2 (фирменный тёмно-зелёный стиль) ---
  const now = new Date();
  const games = [
    { slug: "pixel-quest", title: "Pixel Quest", tagline: "Пиксельное приключение на 20 уровней", status: "released", featured: true, accent: "#76b900",
      description: "<p>Ретро-платформер про храброго пикселя. 20 уровней, секреты, боссы и честная сложность.</p>" },
    { slug: "neon-racer", title: "Neon Racer", tagline: "Синтвейв-гонки по неоновым трассам", status: "soon", featured: true, accent: "#b6f000",
      description: "<p>Аркадные гонки с процедурными трассами и драйвовым саундтреком. Скоро.</p>" },
    { slug: "cube-lab", title: "Cube Lab", tagline: "Головоломки с кубами и физикой", status: "dev", featured: false, accent: "#38bdf8",
      description: "<p>Медитативные головоломки: двигай кубы, ломай физику, находи выход.</p>" },
  ];
  for (const g of games) {
    await prisma.game.upsert({ where: { slug: g.slug }, update: g, create: g });
  }

  // --- Релизы/патчи/новости (опубликованные) ---
  const releases = [
    { gameSlug: "pixel-quest", kind: "release", title: "Pixel Quest — релиз!", version: "1.0.0", body: "Наша первая игра вышла! Пиксельные приключения, 20 уровней." },
    { gameSlug: "pixel-quest", kind: "patch", title: "Патч баланса", version: "1.0.1", body: "Поправили сложность босса, добавили автосейв." },
    { gameSlug: "neon-racer", kind: "announce", title: "Анонс Neon Racer", body: "Скоро — неоновые гонки с синтвейв-саундтреком." },
    { gameSlug: "cube-lab", kind: "news", title: "Девлог: физика кубов", body: "Небольшой рассказ, как мы делали «сочную» физику кубов." },
  ];
  for (const r of releases) {
    await prisma.gameRelease.create({ data: { ...r, published: true, publishedAt: now } });
  }

  // --- Страница «О студии» (CMS) ---
  await prisma.pageStatic.upsert({
    where: { site_slug: { site: "games", slug: "about" } },
    update: {},
    create: {
      site: "games",
      slug: "about",
      title: "О студии 5MB2",
      html: "<p class='lead'>5MB2 — небольшая инди-студия. Делаем короткие выразительные игры и честные патчи.</p>" +
        "<h2>Как мы работаем</h2><p>Идея → прототип → релиз → патчи по фидбеку игроков.</p>" +
        "<h2>Контакты</h2><p>hello@5mb2.ru · VK: 5mb2online</p>",
    },
  });

  // Немного логов расхода токенов за последние дни (для графиков дашборда).
  const models = ["deepseek-chat", "claude-3-5-sonnet"];
  for (let d = 0; d < 7; d++) {
    for (let i = 0; i < 6; i++) {
      const model = models[Math.floor(Math.random() * models.length)];
      const promptTokens = 500 + Math.floor(Math.random() * 8000);
      const outputTokens = 200 + Math.floor(Math.random() * 2000);
      const costRub = model === "claude-3-5-sonnet" ? 3 + Math.random() * 6 : 0.2 + Math.random() * 1.2;
      await prisma.tokenLog.create({
        data: {
          userId: client.id,
          projectId: Math.random() > 0.5 ? p1.id : p2.id,
          taskType: Math.random() > 0.5 ? "agent" : "seo-audit",
          model,
          promptTokens,
          outputTokens,
          costRub: Math.round(costRub * 100) / 100,
          cached: Math.random() > 0.8,
          createdAt: new Date(Date.now() - d * 24 * 60 * 60 * 1000),
        },
      });
    }
  }

  console.log("Seed готов:");
  console.log("  admin@neobrain.local / password123 (роль admin)");
  console.log("  client@neobrain.local / password123 (роль client)");
}

main().finally(() => prisma.$disconnect());
