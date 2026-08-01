# STUDY-WORKSPACE.md — Chatbot polish → Local Knowledge → Workspace

> **Living task doc.** Born 2026-07-24 from the hands-on chatbot review + Jordy's Workspace vision
> ("a real BIG chatbot": full-screen, history on the left, model picker; our own cheap Mammouth AI,
> Pro only). Guiding principle from Jordy: **don't make everything overly complicated. Build smart
> reusable components, keep the current chatbots light, build Workspace separately on the same
> components.** So: polish first, then a refactor/extraction step with heavy testing, then Workspace.
> Demand rule applies ([[feedback_demand_driven_features]]): each phase ships user-visible value.

## Phase 1 — Chatbot polish (from the 2026-07-24 hands-on review)

- [x] **Over-limit lockout.** Limits refuse via WP_Error → `Meow_MWAI_RefusedException` → REST returns
  `overLimit: true` (HTTP 429) → frontend locks the input (same state GDPR uses). Streaming errors now
  pass refusal messages through instead of the generic "Oops". Demand: wp.org thread "Chat remains
  active after usage limit is reached". Validated live as guest + smoke run green.
- [x] **Config errors gated to editors.** "Chatbot 'x' not found" only renders for `edit_posts`;
  visitors get '' + error_log. Both shortcode call sites.
- [x] **Transient errors not persisted.** `saveMessages` filters `isError`/role error, matching
  `onCommitDiscussions`.
- [x] **Copy button on code blocks.** `pre` override in ChatbotContent.js; hljs-compatible.
- [x] **Visitor-friendly default over-limit messages** (constants/init.php; existing sites keep
  their saved text).
- [x] **Image size cap in messages.** Markdown images rendered unconstrained (a logo filled the
  whole chat viewport). Cap height, click opens full image. (2026-07-24)
- [x] **Empty-state hint.** A bot with no startSentence opens as a dead gray box; show a subtle
  hint in the empty messages area. (2026-07-24)
- [ ] **Chatbot Block page silent mount failure.** On ai.nekod.net/sample-page/ the container divs
  render but no chatbot mounts, no console error. Reproduce with console open, fix. (Suspect:
  inline-params block + site-wide popup of the same botId on one page.)
- [ ] **Emoji download icon + translation error notice**: shipped 08d04199 / bfb7fce5 (2026-07-24).
  Listed for the record; done.

## Phase 2 — Local Knowledge DB (default embeddings env, "zero external services")

Goal: a new **"Internal (WordPress DB)"** embeddings environment; no Pinecone/Qdrant account needed.
Removes the #1 friction on the top Pro conversion driver. Target: default env for new installs once
proven.

- [x] **Tier 1: PHP brute-force cosine.** DONE 2026-07-24: `premium/addons/internal.php`, packed
  float32 BLOBs in `wp_mwai_local_vectors`, batched scan + wpdb flush. Measured on ai.nekod.net:
  1.4ms @ 3 vectors, 210ms @ 5,000 (1536 dims). End-to-end proven: smoke-rag chatbot answered a
  fact that exists only in the local store. Default env for FRESH installs (first in
  `embeddings_envs` defaults); existing sites unaffected.
- [ ] **Dimension control.** Matryoshka truncation option (512/768) to cut storage + CPU 2-3x.
- [ ] **Tier 2: MariaDB native VECTOR (11.7+).** Auto-detect; HNSW index, same env type. Optional,
  later.
- [ ] **Hybrid bonus.** Local content column makes keyword (FULLTEXT) + vector RRF fusion trivial;
  quietly answers the Qdrant hybrid ask (swimgeek) provider-neutrally.
- [x] **Smoke coverage.** DONE 2026-07-24: RAGCHAT check in `test-smoke.js` (site-level + `rag`
  arg) on the smoke-rag fixture bot wired to Internal env intern01. Validated against a simulated
  dropped-context regression (broken query_vectors → FAIL, restored → PASS).
- [x] **UX.** DONE 2026-07-24: "Internal (WordPress DB)" card first in the env chooser (badge
  "Simplest"), no API key/server fields, info notice instead. Migration: none needed.

## Phase 3 — Component refactor + heavy testing (the step BEFORE Workspace)

Keep current chatbots light; extract reusable pieces Workspace will share. Overlaps consolidation
item #7 (ChatbotContext decomposition): this phase funds it.

- [x] **Inventory pass.** DONE 2026-07-24 (findings below).

### Inventory findings (2026-07-24)

**The coupling is concentrated in ONE file.** `ChatbotContext.js` (1564 lines) is the only heavy
provider. Everything else is either already decoupled or a thin display consumer. This is the good case:
we extract logic from one file, not untangle a web.

**Already shared / reusable as-is (no work):**
- `helpers.js` — the real network layer (`mwaiFetch`, `mwaiFetchUpload`, `mwaiHandleRes`), zero context,
  already imported by both contexts. This IS the streaming glue; nothing to extract.
- `helpers/tokenManager.js` — nonce singleton, already shared.
- `MwaiAPI.js` — the `window.MwaiAPI` singleton + filter/action bus. The bridge the two contexts talk
  through. Already global.
- `ChatbotContent.js` (236), `ChatbotEvents.js` (291), `ChatbotSpinners.js`, `AudioVisualizer.js` —
  pure props-driven, no context. Drop into Workspace directly.

**The whole history sidebar is already independent.** `DiscussionsContext.js` (415) makes ZERO
`useChatbotContext()` calls — it takes a `system` prop (botId, restNonce), hits
`mwai-ui/v1/discussions/{list,edit,delete}` itself, and reaches the chatbot only via
`MwaiAPI.getChatbot()`. `DiscussionsUI.js` + `DiscussionsSystem.js` ride on it. **Workspace's left
column = mount `DiscussionsSystem` with a `system` prop. Reuse, don't rebuild.**

**The one real extraction — the submit/stream state machine.** Inside `ChatbotContext` these members
are the reusable "chat session" logic, tangled together but separable from all the display params:
`onSubmit` (L867, the streaming submit), `addErrorMessage`/`retryLastQuery`/`lastFailedQuery`, `locked`,
`saveMessages` (localStorage), `refreshRestNonce`, the file-upload callbacks
(`onFileUpload`/`onUploadFile`/`onMultiFileUpload`), `messages`/`chatId`/`sessionId`/`previousResponseId`.
It needs only: botId, customId, sessionId, chatId, restNonce, and a **body-params bag** (envId, model,
temperature, mcpServers…) — NOT the ~70 display fields (aiName, avatars, theme, icon…) that make up the
rest of `state`. Backend already accepts those body params on `chats/submit` (MWAI_CHATBOT_SERVER_PARAMS),
so per-conversation model switching needs no server change.

**Thin display consumers (read state + call actions; ~11 files):** ChatbotUI, ChatbotBody, ChatbotReply,
ChatbotName, ChatbotHeader, ChatbotTrigger, ChatbotInput, ChatbotSubmit, ChatUploadIcon, ChatClearIcon,
MwaiFiles, plus TerminalMessages. These read a context shape; they don't care WHO provides it.

**Cross-surface consumers to not break:** `screens/chatbots/Chatbots.js` mounts `ChatbotSystem` (admin
preview); `premium/js/forms/FormSubmit.js` imports the `MwaiAPI` singleton. Both stay valid under the plan.

### Extraction strategy (chosen: hook, not rewrite)

Per Jordy's "keep chatbots light, don't overcomplicate, no behavior change": extract the LOGIC into a
shared hook, keep the DISPLAY components as context-consumers, and give Workspace its own thin context of
the same shape. The chatbot keeps using `ChatbotContext` (now internally delegating), so its behavior is
byte-identical; Workspace supplies a compatible context that reuses the hook + the discussions sidebar.

- [x] **Extract `useChatSession()`** into `app/js/components/chat/` — the submit/stream/error/lock/upload/
  localStorage machine listed above, parameterized by `{ botId, customId, session, restNonce, bodyParams }`.
  `ChatbotContext` refactors to consume it and spread the result into its existing `state`/`actions` (no
  API change to any consumer). This is the only behavior-sensitive change; it lands behind the smoke gate.
  **Slice one DONE 2026-07-24: `useRestNonce`** (`app/js/components/chat/useRestNonce.js`): nonce state +
  ref + tokenManager subscription + `updateToken` + `refreshRestNonce`(start_session), previously
  duplicated VERBATIM in ChatbotContext and DiscussionsContext (3 more inline `handleTokenUpdate` copies
  removed there). Both contexts consume it now. Verified: full smoke gate 35/0, live popup chat green,
  chatbot.js size unchanged (303K). **Slice two DONE 2026-07-24: `useChatUploads`** (single + multi upload state, progress, the 6
  callbacks against mwai-ui/v1/files/upload), moved verbatim; ChatbotContext passes addErrorMessage
  as onError and its clear-errors behavior as onBeforeUpload. Gate 35/0, build clean, bundle 303K
  unchanged, eslint errors down 54 → 52 (both remaining top offenders pre-exist, incl. the dormant
  `setContentType` no-undef in updateComponentConfig, ChatbotContext ~L1284, worth a fix someday).
  Uploads hand-tested by Jordy in the browser (2026-07-24): working.
  **Slice three DONE 2026-07-24: the full `useChatSession`** (`app/js/components/chat/useChatSession.js`,
  616 lines) composing useRestNonce + useChatUploads and owning messages/chatId/busy/locked/error state,
  localStorage persistence, the streaming onSubmit, and the serverReply effect. Surface behaviors are
  injected (makeInitialMessages, onQueryStart, onCleared, onActions/onShortcuts/onBlocks), so the
  chatbot's eval-actions and shortcut/block handling stay chatbot-only while Workspace passes its own.
  ChatbotContext went 1564 → 881 lines and exposes an IDENTICAL state/actions API. Verified: gate 35/0,
  live browser chat (start sentence + streamed reply), bundle 303K unchanged. Phase 3 extraction COMPLETE.
- [~] **Verify no chatbot behavior change.** (Gate + live chat green after each slice; the broader
  hands-on pass across popup/inline/discussions and 2-3 themes remains before calling the phase closed.)
  Original item: Full smoke gate + hands-on popup/inline/discussions across
  2-3 themes. `git diff` on `chatbot.js` bundle behavior must be nil. This is the gate for the whole phase.
- [ ] **Confirm reusables need no change:** ChatbotContent, ChatbotEvents, DiscussionsSystem/UI/Context,
  helpers, tokenManager, MwaiAPI — used by Workspace as-is. (Inventory says yes; confirm at build time.)
- [ ] **Bundle discipline.** Workspace gets its own webpack entry (`workspace: './app/js/workspace.js'`,
  own `chunkLoadingGlobal`) — the config already has 4 parallel entries, so this is ~5 lines. Verify
  `chatbot.js` size does not grow from the `useChatSession` extraction.

## Phase 4 — Workspace v1 (Pro only, admins only)

The pitch: "your own ChatGPT: every model, your API keys, no per-seat subscription." Full-screen,
history left, generous spacing. TypingMind/LibreChat/Mammouth class, inside WordPress.

**Design direction (Jordy, 2026-07-24):**
- **Structure like TypingMind** (left sidebar with chats, clean center pane, model picker up top),
  but "extremely pretty": TypingMind's layout is the reference, NOT its level of refinement. We do
  better. Bespoke design system for this surface (CSS variables, not NekoUI widgets: this is a
  product surface, not an admin form).
- **Two themes only for v1: dark and light.** A theme toggle, both first-class. No full theming
  engine for now.
- **Configurable accent color, Meow blue by default** (`--neko-blue`, hsl 217 80% 42%; brightened to
  62% lightness on dark). A per-user preference like the theme; the mock ships 5 swatches (blue,
  brass, teal, rose, violet). Approved by Jordy on the mock, 2026-07-24.
- **Mock approved 2026-07-24** (`labs/workspace-mock.html`, view at
  ai.nekod.net/wp-content/plugins/ai-engine-pro/labs/workspace-mock.html). It is the design
  reference for the real build.
- **Settings are PER WORDPRESS USER** (user meta, e.g. `mwai_workspace_prefs`: theme, default
  env/model, sidebar state…). AI Engine Settings barely configures Workspace itself; later it becomes
  the place where admins LIMIT what users can set (allowed envs/models, spend caps per user). For v1:
  admins only, so limits can wait.

- [x] **Skeleton.** SHIPPED 2026-07-24: `module_workspace` toggle (Modules > Chatbots & Knowledge,
  Pro), full-screen admin page `admin.php?page=mwai_workspace` under Meow Apps (chrome hidden),
  own webpack entry (`app/workspace.js`, 104K, chunk global wpJsonMwaiWorkspace), dark/light +
  accent via per-user prefs (user meta `mwai_workspace_prefs`, REST `mwai/v1/workspace/prefs`),
  `premium/workspace.css` from the approved mock. Runs on the internal bot `mwai_workspace`
  (mwai_internal_chatbot filter, based on the default bot, admin-gated).
- [x] **Chat pane** SHIPPED 2026-07-24 on `useChatSession` (streaming, markdown via the shared
  ChatbotContent incl. code copy, error rendering, thinking dots, autoscroll).
- [x] **History sidebar** SHIPPED 2026-07-24 on the discussions REST (list by botId, date groups,
  client search, AI titles, inline rename, two-step delete, new chat, auto-refresh after replies).
- [x] **Model picker** SHIPPED 2026-07-24: envs + chat models grouped with provider dots
  (catalog built server-side with the smoke-gate filtering); selection is sent per-request as
  envId/model body params and remembered in prefs. Advanced params (temperature…) still open.
- [x] **Icon rail + collapsible panel** SHIPPED 2026-07-24 (Jordy's TypingMind-column ask): far-left
  rail always visible (Nyao logo, accent + New chat, Chats/Prompts/Agents-soon, Settings + Admin at
  bottom, 10px labels); the old sidebar is now a sliding panel (collapse chevron in header; clicking
  the active rail item toggles; collapsed state persists in prefs). Theme + accent moved from the
  user card into the Settings panel (segmented Dark/Light + swatch row). Agents = coming-soon panel
  (chat-nyao-2 robot cat). Access points shipped same day: Admin Bar entry (on by default, toggle in
  Settings > Others > Admin Bar), NekoHeader button, Meow Apps menu entry removed (hidden page +
  admin_title filter).
- [x] **Prompt library v1** SHIPPED 2026-07-24: per-user saved prompts (title + text, in
  `mwai_workspace_prefs`, sanitized server-side, capped 200×20k chars); one click inserts into the
  composer (appends if text present) and focuses it; inline editor, two-step delete.
- [x] **Composer toolbar (TypingMind-style features + pins)** SHIPPED 2026-07-24: [+] menu listing
  Attach files / Knowledge / MCP Servers / Functions with per-feature pin toggles (pins persist in
  prefs; default pinned: uploads + knowledge); an ACTIVE feature's icon always shows even unpinned,
  with a count badge. Popovers anchored above the composer, click-outside + focus-composer close.
- [x] **Uploads** SHIPPED 2026-07-24: multi-upload (8 max) via paperclip, drag/drop over the whole
  pane (dashed overlay), paste; chips with image thumbnails/doc icons, per-file progress, remove;
  image previews inside the sent bubble; over-limit and invalid-file handled with friendly notices;
  soft amber warning when the model lacks the vision tag (never blocks). Verified: 6 files at once
  (3 PNG read correctly by GPT-5.6 Sol), txt content extracted server-side and answered from.
- [x] **Knowledge** SHIPPED 2026-07-24: brain popover picks an embeddings env per conversation
  (None + list, persists as default in prefs), sent as embeddingsEnvId body param (same channel as
  chatbots). Verified both ways with the Internal DB: connected → answers the secret passphrase;
  None → does not know it.
- [x] **MCP Servers + Functions** SHIPPED 2026-07-24: plug/braces popovers (multi-select from
  mcp_envs and mwai_functions_list minus editor-assistant), sent per-request as mcpServers/functions
  (function-aware chatbot_query consumes them). Verified: Offbeat Japan MCP listed real latest
  posts via GPT-5.6 Sol; Code Engine functions returned the magic word + relayed a failing sensor
  gracefully; Gemini mid-thread switch degraded gracefully (functions yes, external MCP no: the
  known Gemini gap). Empty/module-off states link to settings.
- [x] **Create Image** BUILT 2026-07-24 (uncommitted, per Jordy's ask; commit with his review): a toggle
  in the composer [+] menu (pinnable, photo icon, status "On"). When on, the request carries
  tools:['image_generation']: the OpenAI engine maps it to the native Responses image tool, Gemini
  Flash Image models output images natively (toggle harmless there), other models get a soft amber
  warning naming capable models. Model catalog gained an `image` capability flag (OpenAI model
  tools + Google image-generation feature). Images come back as markdown (Reply::set_choices saves
  b64 per the image settings), so rendering AND discussion persistence are free; Workspace CSS
  upsizes .mwai-image to 480px with radius+shadow (the shared renderer inline-caps at 220px, hence
  !important). Live QA all green: GPT-5.6 Sol generation; GPT multi-turn edit (snow + lantern added
  to the same scene); upload-an-image + edit (bicycle→red scooter, scene preserved); Gemini 3.1
  Flash Image generation + in-context edit (watercolor of the same kitchen); Claude soft warning;
  trail shows "Generating image..."; images + per-message model chips survive reload.
- [x] **Stop button** SHIPPED 2026-07-24: AbortController in useChatSession (stopGeneration);
  partial reply kept with a STOPPED chip; the turn persists through discussions/truncate, which now
  also creates the row for first-turn stops and keeps stopped/extra.model through sanitization.
- [x] **Per-message model attribution** FIXED 2026-07-24: assistant messages store extra.model
  server-side (discussions), live messages stamped at submit time; chips now show the model that
  actually answered (verified o3 vs GPT-5.6 Sol vs Gemini 3.1 Pro in one thread).
- [x] **Real errors for admins** 2026-07-24: streaming errors pass the actual provider message to
  manage_options users (visitors keep the generic one). Found + fixed en route: error messages were
  invisible in the Workspace (its own admin-notice-hiding CSS matched the .error class; renamed to
  .is-error), and the OpenAI Responses API sent temperature to o-series models (400) — now gated
  like chatml via o1-model/no-temperature tags.
- [x] **QoL batch 2026-07-25:** generated-image lifetime now honors the Files > Expiration setting
  everywhere (the "Never" default silently fell through to the 1h uploaded-files TTL; fixed in
  Reply::save_temp_image_from_b64 for chatbots AND Workspace, replacing an earlier
  workspace-only 10y override); pinned conversations (prefs.pinnedChats, PINNED sidebar group, pin icon in row
  actions); export-as-Markdown icon in row actions; ChatGPT-style time chips between messages
  ("Today 6:36 PM", stored timestamps now saved on discussion messages + kept by truncate);
  a "..." menu on AI messages with the message time and **Branch in new chat** (copies the thread
  up to that message into a new server-backed discussion via the truncate endpoint); keyboard
  shortcuts (Cmd/Ctrl+K new chat in capture phase so WP's own palette stays closed, Esc stops
  generation); hint line documents them. All verified live; smoke gate 35/0.
- [x] **This WordPress (MCP tools as functions)** SHIPPED 2026-07-25 (Jordy's ask; bridge over
  self-MCP for reachability + all-provider coverage): globe entry in the [+] menu with a master
  toggle ("Work on {site}") and per-category checkboxes (Core preselected; counts + tool-name
  tooltips), pinnable, persisted (prefs wpMode/wpCategories). Client sends `wpTools:[categories]`;
  workspace.php stashes it in mwai_internal_chatbot (admin + workspace-bot only), converts the
  matching `mwai_mcp_tools` schemas into functions on mwai_chatbot_query (new
  `Meow_MWAI_Query_Function::from_raw_schema` + rawSchema passthrough in all four serializers),
  and executes via the `mwai_mcp_callback` filter in an mwai_ai_feedback handler (JSON-RPC/MCP
  envelopes unwrapped, Throwable-contained, int call id since handlers type `?int $id`). Tool
  catalog is served by REST (`workspace/wp-tools`) because core registers its tools filter on
  rest_api_init only. maxDepth raised to 20 for workspace scope (find→read→edit→verify burns 5+).
  Verified live: GPT-5.6 Luna counts+lists posts; Claude Opus 5 full write flow (find draft,
  append haiku, read back, ID+permalink); Gemini 3.5 Flash Interactions protocol correct
  (function_result+call_id+previous_interaction_id; the model itself over-calls tools — its
  quirk, stray write failed safely on validation).
- [x] **Anthropic multi-round function calling FIXED plugin-wide 2026-07-25** (found via the
  bridge, affects chatbots too, likely the real cause of Jordy's "Claude re-calls tools" report):
  engines/core.php cleared the feedback blocks on every recursion, so stateless providers
  (Anthropic, Chat Completions, classic Gemini) lost every earlier tool exchange of the turn —
  round 3 sent only round 2's pair, the model re-issued identical calls and tripped the loop
  detector. Blocks now ACCUMULATE for stateless replies (engines replay assistant tool_use +
  tool_result per block, rebuilding the chain) and still reset for stateful ones (OpenAI
  Responses, Google Interactions, Assistants) which hold history server-side.
- [x] **Claude Opus 5 added 2026-07-25** (claude-opus-5, family claude-5, no-temperature,
  1M ctx / 128k out, $5/$25; 'latest' moved off Opus 4.8). Verified live in the Workspace.
- [x] **Image lifetime in the lightbox** SHIPPED 2026-07-25 (Jordy's ask): the lightbox bar shows a
  live 1s-ticking countdown chip driven by the temp-file record ("Expires in 57m 41s", amber under
  10 min, "Kept forever" when the setting is Never, green "In Media Library" for attachments) via
  REST `workspace/image-info` (URL → expires, server-clock offset applied client-side), plus a
  **Save to Media Library** action via `workspace/image-persist`: copies the file into a real
  attachment (wp_upload_bits + wp_insert_attachment + metadata), rewrites the URL in the stored
  discussion messages AND the live thread, then deletes the temp file record. Verified live:
  generated with a 1h TTL → countdown ticks → saved → chip flips to In Media Library → reload
  serves the attachment URL from the rewritten history.
- [x] **Batch 2026-07-25 (post-review):** WordPress Tools rename + official WP mark icon (the
  globe didn't read as WordPress; filled path overrides the stroke-based icon set); advanced
  model params in the topbar (temperature slider, hidden-locked on no-temperature models;
  reasoning-effort segmented control on reasoning models; per-user prefs.advanced; build_envs now
  exposes the reasoning/no-temperature tags); server-side conversation search (the shared
  discussions chats_query 'preview' filter now matches title too, and the UI list endpoint takes
  a `search` param — usable by the chatbot discussions UI as well; Workspace sidebar debounces
  into it past the loaded 50); Settings > Workspace section (workspace_image/_wp_tools/_mcp/
  _functions/_knowledge availability toggles, defaults on, enforced server-side in chatbot_query +
  wpTools stash + hidden in the composer). Verified live incl. toggling Create Image off.
  Full gaps list mirrored in ~/Desktop/WORKSPACE-MISSING.md (permission dialogs = top item).
- [x] **Freemium split** SHIPPED 2026-07-25 (Jordy's call, matching the strategy discussion):
  the Workspace moved to the free core (classes/modules/workspace.php, app/workspace.css,
  Meow_MWAI_Modules_Workspace, instantiated from classes/core.php on module_workspace, no more
  requirePro on the module toggle). Knowledge, MCP Servers and Functions stay Pro: their Settings
  checkboxes are requirePro, the server computes effective flags (option AND is_registered) and
  strips them from the query, and unregistered installs see them as tasteful locked "Pro" rows in
  the [+] menu linking to meowapps.com. WordPress Tools (core, free MCP) and Create Image stay
  free. Settings > Workspace gained a Roles block stating admins-only for now.
- [x] **Tool approval dialogs** SHIPPED 2026-07-25 (the top item of the gaps list): any
  WordPress Tool that can change the site (MCP readOnlyHint annotations; name-heuristic fallback,
  unknown verbs count as writes) pauses the turn via Meow_MWAI_ApprovalRequiredException. The
  pipeline turns it into an in-thread approval card (custom UI: tool name, args JSON, Deny /
  Allow Once / Always Allow in This Chat), stores nothing while pending, and the decision resumes
  the turn through the edit-message truncate+resubmit primitive with the decision riding along
  (wpToolsAllowed/wpToolsDenied params). A per-turn transient cache returns already-executed
  write results on re-runs so approving call 2 never re-executes call 1 (no duplicate posts).
  Verified live: create paused, Allow Once created exactly one post; Deny handed the refusal to
  the AI ("no changes were made"); Always Allow let a two-post turn complete with one card.
  Stale-closure gotcha: the resume MUST go through a latest-ref or it submits pre-decision atts.
- [x] **Conversation folders** SHIPPED 2026-07-25: per-user named folders in the sidebar
  (prefs.folders, max 30, sanitized server-side), one folder per conversation, assigned via a
  folder menu on each row (with inline "New folder..."), collapsible groups with count chips,
  hover rename + two-step delete (deleting releases the chats back to the date groups), folder
  membership wins over date groups and Pinned, folders with no matches hide during search.
  Verified live incl. collapse-state persistence across reloads.
- [ ] **Pinned conversations vs retention:** the discussions cleanup deletes old rows regardless
  of Workspace pins (a pinned chat vanished after retention ran). Either exclude pinned chatIds
  from cleanup or prune dead pins from prefs. Small, worth doing with folders.
- [ ] **Roles beyond admin** parked (Jordy 2026-07-25): needs a real permissions design first,
  because by default a user could pick any environment, model, and feature. The settings surface
  for restricting envs/models/features per role is the prerequisite, not the role toggle itself.
- [~] **v1 polish:** per-conversation cost badge wired. Still open: folders (pins shipped),
  self-hosted fonts (Google Fonts CDN for now, admin page only), image previews for reloaded
  conversations (server stores text only).
  **QA 2026-07-24 (all green):** two full passes incl. adversarial (corrupt PNG rejected with a
  friendly error, 8-file limit notice, stop before first token, [ERROR] hook, light+dark for all
  new UI, pin/unpin round-trips); smoke gate 35/0 after each batch.
- [ ] **Later (v2+):** roles beyond admin, front-end variant, agents, shared/team conversations.

## Standing rules for this program

- One commit per finished, tested item; smoke gate (`node labs/tests/test-smoke.js`) before each
  chatbot/engine-path commit.
- Bundles stay out of fix commits (Nekofy sweeps them at release).
- Anything that grows `chatbot.js` for Workspace's benefit is wrong; separate entry.
- Tick items here as they land; /pulse may surface the top unchecked item.
