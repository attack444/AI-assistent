// =============================================================================
//  Точка входа web-сервиса.
// =============================================================================
const app = require("./app");
const config = require("./config");

// Не даём стартовать в проде с небезопасной конфигурацией.
config.assertSafeConfig();

const server = app.listen(config.port, "0.0.0.0", () => {
  console.log(`[web] слушает http://0.0.0.0:${config.port} (env=${config.nodeEnv})`);
});

// Аккуратное завершение (важно в Docker: не терять запросы при рестарте).
for (const sig of ["SIGINT", "SIGTERM"]) {
  process.on(sig, () => {
    console.log(`[web] ${sig} — завершаюсь`);
    server.close(() => process.exit(0));
  });
}
