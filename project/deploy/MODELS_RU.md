# Модели AI — что за что отвечает

Кратко для владельца VPS (Вячеслав).

## Матрица задач

| Задача | Кто справляется | Нужен ли DeepSeek |
|--------|-----------------|-------------------|
| «Привет», общие вопросы | Ollama `qwen2.5:1.5b` | Нет |
| Обзор сайта («что скажешь о сайте») | Ollama + данные с диска | Желательно для качества |
| Правка HTML/CSS/PHP, `str_replace` | **DeepSeek** (tools) | **Да** |
| WordPress: URL, права, белый экран | **DeepSeek** + hosting tools | **Да** |
| VS Code чат → правки на VPS | **DeepSeek** | **Да** |
| Публичный чат на `/sites/ai/` | Ollama → DeepSeek fallback | Для умного ответа — да |
| Деплой ZIP на витрине | Без LLM | Нет |

**Вывод:** `1.5b` — «дешёвый болтун с контекстом».  
Чтобы ассистент **делал** (правил файлы), нужен **DeepSeek** или локальная ≥`qwen2.5:7b` (много RAM).

## Как сейчас роутится (панель / расширение)

```
чат без правок  →  Ollama 1.5b  →  (если упала) DeepSeek → Groq
правки / tools  →  DeepSeek  →  Groq  →  локальный agent (только если модель умеет tools)
```

`qwen2.5:1.5b` **не умеет tools API** → раньше был HTTP 400. Теперь tools туда не шлём.

## Стоимость (ориентир)

| Вариант | Деньги | Качество правок |
|---------|--------|-----------------|
| Только Ollama 1.5b | ~0 ₽ | Слабо: план текстом, не правит |
| Ollama + **DeepSeek** | обычно **десятки–пара сотен ₽/мес** при личном использовании | Хорошо для сайтов/кода |
| Ollama 7b на VPS | RAM ~6–8 ГБ+, электричество VPS | Средне+, без ключей |
| Groq | free tier / дешево | Часто недоступен из РФ без прокси |

DeepSeek для твоего сценария (1–2 сайта, VS Code, панель) — **экономически выгодно**: дешевле часа ручной правки.

## Подключение DeepSeek (обязательный шаг для «как задумано»)

1. Ключ: https://platform.deepseek.com  
2. В `project/.env` на VPS:
```bash
DEEPSEEK_API_KEY=sk-...
DEEPSEEK_MODEL=deepseek-chat
LLM_PREFER_FREE=1
FREE_LLM_MODEL=qwen2.5:1.5b
```
3. `bash project/deploy/bootstrap-update.sh`

Проверка: в панели/расширении статус `deepseek: true`, при команде «исправь index» идут tool_call, не ошибка 400.

## Бесплатно только Ollama

```bash
curl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/install-free-llm.sh | bash
```

Мало RAM: `FREE_LLM_MODEL=qwen2.5:0.5b`  
Хочешь локальные tools без облака: `FREE_LLM_MODEL=qwen2.5:7b` + `ollama pull qwen2.5:7b`
