// =============================================================================
//  ВАЛИДАЦИЯ входных данных через zod. Никогда не доверяем тому, что прислал
//  клиент. validate(schema) проверяет req.body и заменяет его безопасной
//  (очищенной) версией или возвращает 400 с понятной ошибкой.
// =============================================================================
function validate(schema) {
  return (req, res, next) => {
    const result = schema.safeParse(req.body);
    if (!result.success) {
      return res.status(400).json({
        error: "неверные данные",
        details: result.error.issues.map((i) => ({
          path: i.path.join("."),
          message: i.message,
        })),
      });
    }
    req.body = result.data; // дальше используем уже проверенные данные
    next();
  };
}

module.exports = { validate };
