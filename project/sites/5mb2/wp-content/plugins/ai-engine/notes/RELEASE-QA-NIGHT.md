# Release QA — overnight log

Working state for the recurring pre-release QA loop. **Read this first every pass, append to it
before the pass ends.** Without it, each firing re-tests the same three things.

Baseline: `master` at `68d1e938`. Test site: https://ai.nekod.net/ (wp-admin available).
PHP log: `~/sites/ai/logs/php/error.log`.

## Log baseline

Record the line count at the start of every pass, and only treat *new* lines as findings.
Ignore anything mentioning `code-engine`, `mwseo`, or another plugin's path — Jordy works in
those in parallel and their fatals are not ours.

| Pass | Log lines at start | New AI Engine lines |
|---|---|---|
| 1-8 | 6974 → 6995 | 3 real (all fixed or explained) |
| 9 | 6995 | 0 |
| 10 | 6995 | 0 |
| 11 | 6995 | 0 |
| 12 | 6995 | 1 — the advisor_daily guest-limit line, see findings |
| 13 | 6996 | 0 |
| 14 | 6996 | 0 |

## Fixes made so far (uncommitted, waiting for Jordy's release)

- `themes/sass/_common.scss` + 4 compiled themes — mobile soft keyboard hid the chatbot input in
  fullscreen: the `.mwai-fullscreen` shell out-specified the mobile block and kept `100dvh`.
  **Confirmed on a real device** (Jordy, iPhone, Chrome iOS): input fully visible directly above the
  keyboard, and the chatbot fills the screen once the keyboard is dismissed. The white strip that
  appears between the input and the keyboard is iOS's AutoFill accessory bar (passwords / payment /
  addresses + dismiss button), which is browser UI drawn below the visual viewport — not our page
  showing through, and not something we can paint under or remove. Do not chase it in a later pass.
- `app/js/chatbot/ChatbotUI.js` — visual-viewport hook now also active for inline fullscreen.
- `premium/forms.php` — `$query` / `$stream` undefined in the `rest_submit()` catch block.
- `premium/embeddings.php` — `sanitize_sort()` logged an error on *every* Knowledge search
  (`score` accessor); now only validated on the path that actually uses ORDER BY.
- `app/js/screens/Embeddings/Embeddings.js` + `app/i18n.js` — Query Mode showed "No results"
  before any search had run; new `SEARCH_EMBEDDINGS_PROMPT` string, plus dead code removed.
- `classes/core.php:332` + `:353` — **CJK posts longer than `context_max_length` produced empty
  content.** `clean_sentences()` split on `/(?<=[.?!。．！？])\s+/u`, requiring whitespace *after* the
  punctuation. Japanese puts none after `。`, so a whole article was one "sentence", which the loop
  then skipped for being over maxLength, leaving `$uniqueSentences` empty and returning `''`.
  Found from Kenichi Wada's support thread (wada-keiei.com): post 26078 stale forever, "content is
  empty" logged, standard editor, `post_content` populated. It is also the real cause of his
  original 318 → 317 vector loss, not the `mwai_pre_post_content` filter he was asked to remove.
  Two changes: split CJK punctuation with no whitespace required (Latin still requires it, so
  "3.14" and "e.g." are untouched), and never let an over-long single sentence be dropped —
  truncate it instead.
  Verified against the byte-identical extracted function: realistic 10,092-char unique Japanese
  article 0 → 4,196 chars; Thai with no punctuation 0 → 4,096; empty and whitespace-only inputs
  still return empty. **Eight Latin samples are byte-identical to the old output** (plain, URLs,
  newlines, abbreviations, duplicates, 300 unique sentences, html-ish, no-punctuation), so the
  checksum — `wp_hash( $content . $title . $url )`, `core.php:830` — does not churn on non-CJK
  sites and they will not mass re-sync on update. CJK sites *will* re-sync once, which is the point.
  Also confirmed live through `/helpers/post_content` on a throwaway Japanese draft (since deleted):
  output now has spaces between sentences, which only the new split produces.
- `app/js/screens/queries/Insights.js` + `app/i18n.js` — the Top Tools panel added by 18a8562a
  hardcoded four English strings (block title, the "N failed" label, and both tooltips) while its
  sibling block 90 lines above uses `i18n.COMMON.ACTIVITY`. Now `TOP_TOOLS_7_DAYS`,
  `TOOL_CALLS_MIXED` (positional `%1$s`/`%2$s`), `TOOL_CALLS_ALL_FINE`, `TOOL_CALLS_FAILED`,
  with `sprintf` imported from `wp.i18n` as elsewhere in the codebase.

## Already verified — do NOT re-test unless something changes

- Front-end chatbot round trip (OpenAI), streaming, reply, no JS errors, logged in Insights.
- Anthropic (Claude Fable 5) via admin preview. Mistral + Google/Gemini 3.1 Pro via `simpleTextQuery`.
- Function calling: single call, **two calls in one turn**, argument binding (`quote_bridge_demo`
  returned every arg incl. `style`), required-arg handling.
- MCP over HTTP: `mcp_ping`, `wp_count_posts`, `wp_get_posts` → logged in MCP Logs with client + duration.
- Workspace + WordPress tools (41 tools): answered 7 published posts, matching MCP. Honest
  "I can't access the site tools" when tools are off.
- RAG: `smoke-rag` bot retrieved the passphrase; direct search scores 78.3 / 60.6 / 39.4%.
- Knowledge Query Mode: all three empty states + real results.
- Log export data path: `/system/logs/list` paging, sort by price desc, filter scope=chatbot.
- Vision: `mwai_vision` on an existing attachment.
- Discussions list + detail pane (model, bot, chat id, session).
- Admin tabs render clean: Dashboard, Modules, Chatbots, Discussions, Knowledge, Forms, Insights
  (Query + MCP Logs), Settings, Dev Tools, License, Playground, Workspace.
- Mobile fix regression matrix: 4 themes × {mobile popup, mobile popup fullscreen, mobile inline
  fullscreen, desktop fullscreen} + the no-vars fallback. Desktop and fallback byte-identical to before.
- Tasks: `sync_remote_urls` exceeded max_retries and **kept its `next_run`** → the parked-task
  guard (15f2a6b7) holds.

## Not yet covered — pull the next item from here each pass

- [ ] Chatbot file upload (image + PDF) from the front end, then a question about the file.
- [ ] Content Generator (Content button) — one short generation, check the result lands in a draft.
- [ ] Images Generator — one small image, check the media item and the Insights row.
- [ ] AI Forms rendered on a real front-end page (shortcode `[mwai_form id="110"]`), submit path.
- [ ] Magic Wand / AI Copilot in the block editor (suggested title, rewrite selection).
- [ ] Search module (Context-Aware and Smart Search) if it can be enabled without disruption.
- [~] Chatbot themes visual pass — attempted in pass 11, **low signal, do not repeat as-is**. A
      hand-written static replica in iframes renders without the `--mwai-*` variables the plugin
      injects, so the colours are not what a user sees and the name labels lay out wrong; the
      "findings" would be replica artifacts. The admin Chatbots > Themes toggle is a value *editor*,
      not a preview gallery, and clicking into its fields risks persisting a change. What was
      established instead (read-only, via `/settings/themes`): all four themes are registered,
      `type: internal`, with empty `settings`/`style` — i.e. pristine CSS defaults, no corrupt or
      non-colour values. A genuine visual pass needs the real front-end chatbot with one theme
      applied at a time, which means mutating a chatbot's theme setting — out of bounds here.
- [ ] Chatbot params that are easy to get wrong: `maxHeight`, `centerOpen`, `iconBubble`,
      `windowAnimation`, container `osx` — mostly a CSS/DOM check, no AI calls needed.
- [x] Discussions: delete a single discussion — done in pass 12 on a throwaway row I had created
      myself in pass 6 ("Count published posts via site tools"). The ConfirmModal copy is specific
      and correct: title "Delete Selected Discussions", red "This cannot be undone.", then "The
      selected discussions will be permanently deleted." / "The users who own them will lose that
      conversation history in their chatbots and in the Workspace." / "Discussions to be deleted: 1",
      with Cancel as the Enter-bound default. Row gone after confirming, table refreshed, no error.
      `ConfirmModal.js` reviewed too: `lines.filter(Boolean)`, phrase field reset on open, red button
      disabled until the phrase matches.
- [ ] Copy pass on user-facing strings in the recent commits' UI (Insights export modal, Sync panel,
      reset modal, Maintenance block) — typos, em-dashes, unclear wording.
- [ ] i18n sanity: any hardcoded English left in the screens touched by the last 30 commits.
- [ ] Realtime + Assistants: bug-fix-only features, just confirm they still load without errors.
- [ ] Cross-check the last 30 commits below against their code, looking for leftovers: dead code,
      TODOs without dates, debug `error_log`, commented-out blocks.

## Commit review checklist (last 30 — tick when read against the code)

- [x] 68d1e938 HTTP status instead of "could not parse the response" + i18n.ERRORS typo — verified
      live in the browser by stubbing `window.fetch` on `/mwai-ui/v1/chats/submit`. A 502 HTML
      response gives "Your server replied with an HTTP 502 error…"; a 200 non-JSON response gives
      the SERVER_NOT_JSON message; the raw HTML body never reaches the chat bubble, only the
      console. Zero `i18n.ERRORS` leftovers repo-wide, and all five ERROR keys helpers.js reads
      exist in both `app/i18n.js` and the in-file fallback object. Note: streaming is on for this
      site, so the branch exercised was `mwaiServerError(fetchRes, buffer)` in the SSE catch; the
      non-streaming branch shares the same helper.
- [x] 4cf58fef mwai_mcp_callback arity (4 vs 5 args) — clean one-liner, well commented. Checked all
      seven in-repo handlers (mcp-core, mcp-rest, mcp-database, mcp-plugin, mcp-theme, mcp-polylang,
      mcp-woo-commerce): every one registers `10, 4` and declares 4 params, so the extra null is
      harmlessly ignored internally and third-party 5-arity handlers now work in both paths.
      Behaviour already verified live in pass 6.
- [x] 18a8562a CSV/JSON log export + Insights failure count — logic and reasoning sound; export
      data path already verified in pass 1. Found and fixed the hardcoded English in the new Top
      Tools panel (see fixes above). Re-verified in the browser: title "Top Tools (7 days)",
      labels "2 failed" / "1 failed", tooltips "35 succeeded, 2 failed" and "112 calls, no
      failures" — all rendering through i18n with the counts consistent (35+2=37, 30+1=31).
- [ ] fda635a8 REST hardening, MCP OAuth route, guest cookie, Insights scope escape
- [x] 0740c292 Push All follows Sync filters — read and then exercised
      `/helpers/count_posts` + `/helpers/posts_ids` across 8 scenarios. The invariant the commit
      exists to guarantee holds everywhere: count always equals the id-list length (no filter 6/6,
      one category 6/6, category union 6/6, empty categories 0/0). Multi-category behaves as a union
      with no double-counting, so the added `DISTINCT` / `COUNT(DISTINCT p.ID)` are doing their job.
      Multi-status works (publish+draft = 10). `uncategorized` reports 6 where WP reports 7 — the
      gap is the one post carrying `_mwai_embedding_ignore`, deliberately excluded. A bogus language
      returns everything rather than nothing, which is intentional and documented in the commit
      (`taxonomy_exists('language')` is false without Polylang, matching `is_post_language_synced()`).
      Reviewed the SQL: `$taxonomy`/`$alias` interpolations are hardcoded literals from the two call
      sites, slugs go through `%s`, and the join args are merged ahead of the WHERE args so the
      `prepare()` order is right.
- [x] b38f5947 removed module_addons / module_blocks defaults — zero remaining references to either
      key anywhere in source. Stale copies left in existing sites' `mwai_options` rows are inert.
- [ ] a36887b8 Workspace Web Search button
- [ ] c4b616bc AI Forms elapsed timer
- [ ] 15f2a6b7 recurring tasks parked forever  ← verified live, still tick the code read
- [x] f56aceb3 broken third-party filter no longer breaks chat — exercised for real on the front end
      by registering a snippet shaped exactly like the one in the commit message (`return data.nope`,
      a ReferenceError) plus a well-behaved filter *after* it. Result: the broken one was caught and
      logged as `[MWAI] The 'ai.reply' filter threw an error and was skipped. data is not defined`,
      the reply still arrived, **the second filter still ran** (isolation is per-callback, not
      per-tag), and no error bubble reached the visitor. Action isolation verified separately via
      `doAction('translateText')` with two listeners: the throwing one was logged and skipped, the
      second still ran, and nothing propagated to the caller. Note `doAction` is only dispatched by
      the wand actions (correctText, enhanceText, translateText, …) — no chatbot path calls it, so an
      action listener on `ai.reply` never fires. All page-local; a reload cleared it.
- [ ] a82b94da embeddings Type dropdown for Internal env
- [ ] 61fb5502 TLS verification on streaming
- [ ] 9adbb350 MCP gates use manage_options
- [ ] b597ebdd no PHP session on REST
- [ ] ff8232b9 three WPScan fixes
- [ ] bb74911e Workspace unlocked, Knowledge/MCP/Functions stay Pro
- [ ] 8cf28a27 Workspace module Pro option
- [ ] e098ba2d small stored tool-call arguments
- [ ] 6d5a468f Workspace web search toggle persists
- [ ] a11756e9 Workspace web search
- [x] ab81d2eb keepalive frames not logged — the only `Unhandled streaming JSON structure:
      {"type":"keepalive","sequence_number":8}` line in the entire error log is at 26-Jul 15:39:16,
      and the commit landed 26-Jul 17:53:49. Four days of streaming traffic since (including
      tonight's), zero recurrences. The guard returns before the catch-all log for both `keepalive`
      and `ping` types.
- [ ] 718fc4e4 Maintenance block message instead of showSnackbar
- [ ] 3da5fa4e mwai_image removed as Workspace tool
- [ ] 9d98f148 reset modal mentions chatbots and themes

## Reported, NOT built (Jordy's call)

- **Workspace tool calls are absent from MCP Logs.** `classes/modules/workspace.php:616` fires
  `mwai_mcp_callback` directly, bypassing `Meow_MWAI_Labs_MCP::execute_tool()` in `labs/mcp.php:1163`,
  which is the only place `mwai_mcp_tool_called` fires — and that action is the only listener feeding
  `premium/statistics.php:54`. So the 41 Workspace tools (including destructive ones) leave no
  audit trail. Logging feature, not a regression: not shipped during release prep.
- **`advisor_daily` reports success while doing nothing, on any site with a Guests limit.**
  Found in pass 12 from a single new log line at 02:00:26 UTC: `AI Engine: You have reached the
  limit (check the Insights Tab > Limits > Guests).` — the same second as `advisor_daily`'s
  `last_run`, while the task row still read `status: pending, error_count: 0`.
  Chain:
  1. `classes/modules/advisor.php:122` `run_advisor()` runs on cron with no user context, so
     `get_current_user_id()` is 0 and the query is billed as a **guest** — the Guests limit applies
     and rejects it. Precedent for the fix already in the tree: `classes/modules/discussions.php:456`
     and `premium/embeddings.php:1046` both `wp_set_current_user()` an admin before running.
  2. `classes/modules/advisor.php:168` catches and only `error_log()`s — it does not rethrow.
  3. So `run_advisor_task()` (`:98-104`) never sees an exception and returns
     `[ 'ok' => true, 'message' => 'Advisor analysis completed successfully.' ]`.
  Net effect: `mwai_advisor_data` is never updated, the Advisor shows stale or empty
  recommendations forever, and the Tasks UI shows a healthy green row. Nothing surfaces to the admin.
  **Not fixed deliberately:** not a regression from this release (the swallow dates to `1623ecc9`,
  2024-06-07), the root fix changes user attribution and limits accounting on the query path, and
  merely rethrowing would flip a visible task row to red. Jordy's call, after the release.
- **`check_posts_content` still loops `get_post()` per id (server-side half of the memory crash).**
  `classes/rest.php:1501` loads each post with `get_post()`, and WordPress keeps every post object
  in its object cache for the rest of the request, so memory grows linearly with the chunk size.
  The client now chunks at 1,000 (`app/js/helpers-admin.js`), which fixes the reported crash
  (discomedia.nl, 45,180 ids, 256M limit, hard 500). The durable fix is to stop using `get_post()`
  there: a batched `SELECT ID, post_content FROM $wpdb->posts WHERE ID IN (...)` would be memory
  flat and much faster, keeping the `mwai_pre_post_content` filter call per row. Left for the
  maintenance loop since it changes a data path and wants testing on a genuinely large site.
- **Pre-existing hardcoded English (broader i18n cleanup, deliberately not done).** Distinct from
  the pass-10 fix, which was a *new* commit breaking its own sibling's convention. These are old
  and internally consistent, so touching them now is translation churn with no stability gain:
  - `app/js/screens/Embeddings/Environments.js:249,251,291` — "Deployment", the Chroma Cloud vs
    Self-Hosted explanation, "URL of your self-hosted Chroma instance". From `c0468e5be` (2025-11).
  - `app/js/screens/Settings.js:~2035-2100` — the whole Workspace tools block: "Create Image",
    "Web Search", "WordPress Tools", "Knowledge", "MCP Servers", "Functions" and all six of their
    descriptions. All six siblings are hardcoded, so `a11756e9` matched local convention.
- Deleting an embeddings environment leaves it referenced as a fallback, so `sync_remote_urls`
  fails daily with "The fallback environment (envId: iuknbb73) is not available". Clear message,
  test-site data rot, but clearing the reference on delete would be more robust.

## Findings log

Append one line per pass: date, what was exercised, what was found. Keep it short.

- 2026-07-29 passes 1-8: see above. Three real bugs found and fixed, two items reported.
- 2026-07-30 pass 9: read 68d1e938 and verified both new server-error branches in the browser via
  a stubbed fetch (502 HTML, and 200 non-JSON). Both messages correct, no raw body leak, log clean.
  Nothing to fix. Technique worth reusing: stubbing `window.fetch` for one endpoint exercises
  error paths with zero AI spend.
- 2026-07-30 pass 14: verified f56aceb3 and ab81d2eb, both by reproducing the exact failure each
  one fixes rather than only reading them. One AI call. Nothing to fix. Pattern worth reusing:
  for a "stopped X from breaking Y" commit, register the broken thing yourself and confirm Y
  survives; for a "stopped logging Z" commit, grep the whole log and check the last occurrence
  predates the commit.
- 2026-07-30 pass 13: read and verified 0740c292 and b38f5947. Nothing to fix. The count/ids
  agreement check is a good pattern for any commit that adds a filter to paired endpoints.
- 2026-07-30 pass 12: read 9d98f148 (reset modal — all five `RESET_*` keys exist, copy accurate,
  verified by reading `ConfirmModal.js` rather than opening a modal that can wipe this site) and
  0740c292. Exercised the Discussions delete flow on my own throwaway row. Found the
  `advisor_daily` silent-failure chain from one log line — reported, not fixed. Note for later
  passes: Jordy was active on the site around 21:41-21:48 (SwitchBot temperature tests), so
  discussions and log lines from that window are his, not ours.
- 2026-07-30 pass 11: swept every `app/js` / `premium/js` file touched by the last 30 commits for
  prose introduced in JSX props or template literals. Only two hits, both benign: `a82b94da`'s
  `label="Internal (WordPress DB)"` (sits among Pinecone/Chroma proper nouns) and `a11756e9`'s
  Web Search setting (matches its five hardcoded siblings). Nothing to fix. Theme visual pass
  attempted and downgraded — see the queue entry for why. Tooling note: browser tab 908893678's
  screenshot pipeline wedged (CDP `Page.captureScreenshot` timing out even after navigation) while
  JS eval kept working; opened tab 908893700 and continued there. If screenshots start timing out,
  open a fresh tab rather than debugging it.
- 2026-07-30 pass 10: read 4cf58fef and 18a8562a. One fix: hardcoded English in the new Top Tools
  panel, now i18n. Build clean, log clean, zero AI calls this pass. Worth repeating on the
  remaining commits: grep the touched screens for template literals containing prose, since new
  UI is where untranslated strings sneak in.
