// =============================================================================
//  Единственный экземпляр Prisma-клиента на весь процесс (так правильно —
//  не создавать клиент на каждый запрос).
// =============================================================================
const { PrismaClient } = require("@prisma/client");

const prisma = new PrismaClient({
  log: process.env.NODE_ENV === "development" ? ["warn", "error"] : ["error"],
});

module.exports = prisma;
