# Модели AI для РФ

## Бесплатно на сервере (рекомендуем)

**Ollama + Qwen2.5 1.5B** — полностью бесплатно, без ключей и VPN, на твоём VPS.

```bash
curl -fsSL https://raw.githubusercontent.com/attack444/AI-assistent/main/project/deploy/install-free-llm.sh | bash
```

В `.env` (ставит скрипт сам):
```bash
OLLAMA_HOST=http://ollama:11434
FREE_LLM_MODEL=qwen2.5:1.5b
LLM_PREFER_FREE=1
FAST_LLM_MODEL=qwen2.5:1.5b
```

Мало RAM? Более лёгкая:
```bash
FREE_LLM_MODEL=qwen2.5:0.5b bash /opt/ai-helper/project/deploy/install-free-llm.sh
```

Приоритет: **Ollama (free) → DeepSeek (если есть ключ) → Groq**.

Панель и витрина `/sites/ai/` используют одну и ту же схему.

## DeepSeek (платный/дешёвый облачный fallback)

1. Ключ: https://platform.deepseek.com  
2. В `.env`:
```bash
DEEPSEEK_API_KEY=sk-...
DEEPSEEK_MODEL=deepseek-chat
```
3. `bash project/deploy/bootstrap-update.sh`

## Groq

Часто недоступен из РФ без VPN/прокси:
```bash
AI_HELPER_HTTP_PROXY=http://127.0.0.1:7890
GROQ_API_KEY=...
```
