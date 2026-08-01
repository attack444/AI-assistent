# QA-LOOP.md

A periodic, self-driving QA loop for AI Engine. Each run picks **one fresh corner** of the
plugin, tries hard to break it, and either confirms it is solid or proposes a small fix.
Run it from time to time, let it tick every 30 minutes, and read the summaries.

## How to run

```
/loop 30m Run one QA-LOOP round on AI Engine (see QA-LOOP.md): pick an area NOT in the
rounds log below, try a feature/model/environment/UI/code path, be creative, and try to
trick it. Prioritise real user-facing friction over security. Report the result and, if you
find something, propose the smallest fix. Append the round to the log at the bottom of QA-LOOP.md.
```

Stop it anytime by asking to stop the loop (the cron is session-only and also auto-expires after 7 days).

## Why this loop exists

The thing that quietly kills a plugin is not a dramatic bug. It is a user who tries something,
it does not work nicely, and they drop it **without ever saying a word**. This loop hunts for
that: the rough edge, the corrupted output, the confusing error, the "why is this off by one line."

**Security is deliberately not the focus.** Multiple security teams already watch AI Engine.
Over-hardening just adds complexity and new edge cases for real users. If a round wanders into
security, note it and move back to friction and correctness.

## Principles

- **One area per round.** Different from everything in the rounds log. Breadth over depth.
- **Try to trick it.** Malformed input, truncated streams, weird Unicode, huge numbers, empty
  values, unusual-but-real model output. Think like a confused user or a cheap model, not an attacker.
- **Confirming robustness is a win.** Not every round needs a fix. "This corner is solid" is a
  valuable, honest result. Do not manufacture a fix to feel productive.
- **Smallest possible fix.** If a fix is complex, propose the simpler alternative instead. Match
  the surrounding code; reuse patterns already in the repo (e.g. the `min(500, …)` cap convention).
- **Do not churn deliberate behaviour alone.** When a behaviour is intentional and the call is a
  product judgement, present options and ask rather than deciding unilaterally.
- **Leave dated breadcrumbs.** For deprecated/dead paths, compat shims, or deferred decisions, add
  a `// TODO: Re-evaluate after <today + 6 months>` (absolute date, never a version number).
- **Prove it.** Reproduce logic in Node/PHP CLI, or test against the live site, before claiming a
  result. Read code to confirm a suspicion; do not report speculation as fact.

## Guardrails (hard rules)

- Never touch `Version:` / `MWAI_VERSION` / readme `Stable tag:` / the readme `== Changelog ==`
  (all owned by Nekofy).
- Never partial-POST `settings/update` — it replaces the entire options object and wipes envs/keys.
  Always read-modify-write the full object.
- Never commit without explicit approval. Group related changes into one coherent commit; past-tense,
  one-line messages ending with ".". No mention of the assistant.
- Exclude build bundles from diffs (`app/chatbot.js`, `app/index.js`, `app/vendor.js`,
  `premium/forms.js`, `premium/library-search.js`, etc.). Revert orphan bundle rebuilds that have no
  matching source change.
- No em-dashes anywhere.
- Format PHP with `pcf fix <file>` (AI-Engine-only tool). Check the bundle mtime before browser-
  verifying a JS edit; `pnpm build` once if stale.

## Areas to rotate through

Pick one that is under-covered. This list is a starting point, not a limit; invent new angles.

- Chatbot rendering (markdown, code blocks, tables, lists, links, RTL, multibyte, emoji, HTML docs)
- Function calling / feedback loop / MCP tools (malformed args, multi-call, depth, timeouts)
- Models and providers (OpenAI, Anthropic, Google, OpenRouter, Perplexity, custom OpenAI-compatible)
- Environments and model resolution (bare model ids, wrong env, missing pricing)
- Embeddings / Knowledge / vector DBs (Pinecone, Qdrant, Chroma) / Smart Search
- AI Forms (placeholders, multi-file upload, submission)
- Discussions / conversation memory / title generation / history limits
- Usage stats / cost calculation / guest and user limits / the limit message UX
- Streaming / abort / mid-stream errors / stop button
- Content-aware / placeholders / templates
- Admin UI (settings, Playground, chatbot builder, Insights)
- Files / uploads / transcription / image generation and editing
- REST endpoints / parameter validation / sanitisation boundaries
- i18n and translatable strings

## Test environment

- Live site: `https://ai.nekod.net/` (wp-admin available; local via `/etc/hosts`).
- Guest nonce: `POST /wp-json/mwai/v1/start_session` → `restNonce`.
- Chat submit: `POST /wp-json/mwai-ui/v1/chats/submit`.
- Guest usage limits are enforced; to bypass for a test, drop a temporary mu-plugin using the
  `mwai_stats_credits` filter gated on a custom request header, and remove it right after.
- Env ids: OpenAI `9nx9mjyd`, Anthropic `e7944erg`, Google `q6ve6g9k`, Internal Test `intern01`.
- Read-only MCP tools (`wp_get_post`, `wp_get_posts`, `mcp_ping`, …) are safe for probing; never use
  write/destructive MCP tools against the live site during a probe.

## Report format (per round)

1. **Area + trick:** what was tested and how you tried to break it.
2. **Result:** solid (clean bill of health) or issue found.
3. **If an issue:** severity, the smallest fix or a proposal, and whether it was applied or deferred.
4. **Log it:** append a one-line entry below.

## Rounds log

Append newest at the bottom. Keep entries to one line so future rounds can scan and avoid repeats.

- 2026-07-25 Gemini function-call result format + retired-model error hint — fixed both.
- 2026-07-25 Multibyte / CJK / emoji end-to-end pipeline — solid (mb-aware length, exact round-trip).
- 2026-07-25 Bare-URL underscore mangling in chatbot — fixed (URL placeholder protection).
- 2026-07-25 Content-aware injecting non-viewable posts — fixed (publicly-viewable / read_post guard).
- 2026-07-26 Markdown tables / lists rendering — fixed stray `<br/>` before nested lists; `1)` lists noted.
- 2026-07-26 Placeholder engine `[N/A]` catch-all — removed (corrupted content-aware pages + templates).
- 2026-07-26 MCP list-tool limits (`wp_get_posts` etc.) — capped at 500 to avoid big-site timeouts.
- 2026-07-26 Parameter validation (temperature / reasoning / maxTokens) — solid; temp clamp-to-1 noted.
- 2026-07-26 Usage cost for unknown / custom models — solid (records tokens, price null, no crash).
- 2026-07-26 Function-call streaming argument robustness — solid; legacy `function_call` path TODO'd.
