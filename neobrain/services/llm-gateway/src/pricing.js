// =============================================================================
//  ЭКОНОМИКА ТОКЕНОВ — как из токенов получить цену в рублях.
//
//  ФОРМУЛА:
//    себестоимость_USD = (promptTokens/1e6)*ценаВход + (outputTokens/1e6)*ценаВыход
//    цена_RUB = себестоимость_USD * КУРС_USD_RUB * НАЦЕНКА
//
//  Пример (DeepSeek, курс 95 ₽, наценка 1.5):
//    вход 8 000 ток, выход 2 000 ток
//    = (8000/1e6*0.27 + 2000/1e6*1.10) $ = (0.00216 + 0.00220) = 0.00436 $
//    * 95 * 1.5 = 0.62 ₽ за задачу.
//
//  Пример (Claude 3.5 Sonnet, те же токены):
//    = (8000/1e6*3.0 + 2000/1e6*15.0) = (0.024 + 0.030) = 0.054 $
//    * 95 * 1.5 = 7.70 ₽ за задачу.
//
//  Вывод: рутину гоним на дешёвой модели, сложное — на мощной. Разница ~12x.
// =============================================================================
const { MODELS } = require("./models");

const USD_RUB_RATE = Number(process.env.USD_RUB_RATE || 95);
const PRICE_MARKUP = Number(process.env.PRICE_MARKUP || 1.5);

// Грубая оценка числа токенов по тексту ДО обращения к модели
// (для показа клиенту цены заранее). ~4 символа на токен — практичное правило.
function estimateTokens(text) {
  if (!text) return 0;
  return Math.ceil(String(text).length / 4);
}

// Себестоимость в долларах.
function costUsd(modelId, promptTokens, outputTokens) {
  const m = MODELS[modelId] || MODELS.mock;
  return (
    (promptTokens / 1e6) * m.usdPer1MInput +
    (outputTokens / 1e6) * m.usdPer1MOutput
  );
}

// Итоговая цена в рублях (с курсом и наценкой), округлённая до копеек.
function costRub(modelId, promptTokens, outputTokens) {
  const usd = costUsd(modelId, promptTokens, outputTokens);
  const rub = usd * USD_RUB_RATE * PRICE_MARKUP;
  return Math.round(rub * 10000) / 10000; // 4 знака — для мелких задач
}

// Предварительная смета: по входному тексту и ожидаемому размеру ответа.
function quote(modelId, inputText, expectedOutputTokens = 1500) {
  const promptTokens = estimateTokens(inputText);
  const rub = costRub(modelId, promptTokens, expectedOutputTokens);
  return {
    model: modelId,
    promptTokens,
    expectedOutputTokens,
    costRub: rub,
    rate: USD_RUB_RATE,
    markup: PRICE_MARKUP,
  };
}

module.exports = { estimateTokens, costUsd, costRub, quote, USD_RUB_RATE, PRICE_MARKUP };
