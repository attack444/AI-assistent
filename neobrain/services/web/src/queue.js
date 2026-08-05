// =============================================================================
//  Очереди задач (BullMQ). API кладёт задачу в очередь и СРАЗУ возвращает
//  task_id, а тяжёлую работу делает worker-сервис в фоне.
//
//  Очереди:
//    seo-audit    — пакетный SEO-аудит списка URL
//    game-release — публикация релиза игры (рендер, инвалидация кэша)
//    deploy       — автодеплой проекта клиента
// =============================================================================
const { Queue, QueueEvents } = require("bullmq");
const IORedis = require("ioredis");
const config = require("./config");

// BullMQ требует соединение с maxRetriesPerRequest=null.
const connection = new IORedis(config.redisUrl, { maxRetriesPerRequest: null });

const queues = {
  seoAudit: new Queue("seo-audit", { connection }),
  gameRelease: new Queue("game-release", { connection }),
  deploy: new Queue("deploy", { connection }),
};

// Хелпер: добавить задачу и вернуть её id (его отдаём клиенту как task_id).
async function addJob(queueName, data) {
  const queue = queues[queueName];
  if (!queue) throw new Error(`Неизвестная очередь: ${queueName}`);
  const job = await queue.add(queueName, data, {
    attempts: 3, // до 3 попыток при падении
    backoff: { type: "exponential", delay: 2000 },
    removeOnComplete: 1000, // храним историю последних задач
    removeOnFail: 1000,
  });
  return job.id;
}

// Узнать статус задачи по id (для поллинга статуса из UI).
async function getJobStatus(queueName, jobId) {
  const queue = queues[queueName];
  if (!queue) throw new Error(`Неизвестная очередь: ${queueName}`);
  const job = await queue.getJob(jobId);
  if (!job) return { id: jobId, state: "not_found" };
  const state = await job.getState(); // waiting | active | completed | failed ...
  return {
    id: job.id,
    state,
    progress: job.progress,
    result: job.returnvalue || null,
    failedReason: job.failedReason || null,
  };
}

module.exports = { queues, addJob, getJobStatus, connection, QueueEvents };
