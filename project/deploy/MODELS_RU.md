# Модели AI для РФ

## Что ставим по умолчанию

**DeepSeek** (`deepseek-chat`) — лучший бесплатный/дешёвый вариант, **доступен из России** без VPN.

1. Ключ: https://platform.deepseek.com  
2. В `/opt/ai-helper/project/.env`:
```bash
DEEPSEEK_API_KEY=sk-...
DEEPSEEK_MODEL=deepseek-chat
LLM_MODEL=deepseek-chat
```
3. `bash /opt/ai-helper/project/deploy/update.sh`

Варианты DeepSeek:
- `deepseek-chat` — общая мощная (рекомендуем)
- `deepseek-coder` — упор на код
- `deepseek-reasoner` — «думающая» (медленнее)

## Groq и другие (часто блокируются)

В коде уже есть прокси. Если поднимешь VPN/прокси на VPS:
```bash
AI_HELPER_HTTP_PROXY=http://127.0.0.1:7890
GROQ_API_KEY=...
```

## Локально на сервере (Ollama)

Нужен GPU или мощный CPU. Без GPU тяжело:
```bash
ollama pull qwen2.5-coder:14b
```
DeepSeek через API обычно выгоднее по качеству/цене.
