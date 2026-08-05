// =============================================================================
//  Проекты клиента: список и создание. Плюс постановка автодеплоя в очередь.
// =============================================================================
const { z } = require("zod");
const prisma = require("../db");
const { addJob } = require("../queue");

const createSchema = z.object({
  name: z.string().min(1).max(120),
  repoUrl: z.string().url().optional(),
  domain: z.string().max(200).optional(),
});

async function list(req, res) {
  const projects = await prisma.project.findMany({
    where: { ownerId: req.user.id },
    orderBy: { createdAt: "desc" },
  });
  res.json({ projects });
}

async function create(req, res) {
  const { name, repoUrl, domain } = req.body;
  const project = await prisma.project.create({
    data: { ownerId: req.user.id, name, repoUrl, domain },
  });
  res.json({ ok: true, project });
}

// Автодеплой: кладём задачу в очередь, worker выполнит git pull + build + up.
async function deploy(req, res) {
  const project = await prisma.project.findFirst({
    where: { id: req.params.id, ownerId: req.user.id },
  });
  if (!project) return res.status(404).json({ error: "проект не найден" });
  const taskId = await addJob("deploy", { projectId: project.id, repoUrl: project.repoUrl });
  res.status(202).json({ ok: true, task_id: taskId, queue: "deploy" });
}

module.exports = { list, create, deploy, createSchema };
