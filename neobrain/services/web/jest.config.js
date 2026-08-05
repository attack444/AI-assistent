// Конфиг Jest для web-сервиса.
module.exports = {
  testEnvironment: "node",
  testTimeout: 20000,
  // Тесты трогают реальные Postgres/Redis (тестовая БД) — не параллелим,
  // чтобы не мешать друг другу общими таблицами.
  maxWorkers: 1,
};
