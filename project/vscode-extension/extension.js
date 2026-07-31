/**
 * AI Helper VS Code Extension v1.3.1
 *
 * - Setup wizard: password / site / auto-sync
 * - Chat → VPS panel API with site context (agent edits live site files)
 * - Save file → POST /sites/sync (instant live on nginx)
 * - Apply code block → write to site or local editor
 * - Status shows free model + whether tools/cloud are available
 */

const vscode = require('vscode');
const http = require('http');
const https = require('https');
const path = require('path');

// ── Config ────────────────────────────────────────────────────────────────────

function cfg() {
    return vscode.workspace.getConfiguration('aiHelper');
}
function apiBase() {
    return String(cfg().get('apiUrl', 'http://127.0.0.1:8502')).replace(/\/$/, '');
}
function chatBase() {
    return String(cfg().get('chatUrl', 'http://127.0.0.1')).replace(/\/$/, '');
}
function getToken() {
    return String(cfg().get('token', '') || '').trim();
}
function getSite() {
    return String(cfg().get('site', '') || '').trim();
}
function autoSyncOn() {
    return !!cfg().get('autoSyncOnSave', true);
}

function authHeaders(extra = {}) {
    const h = { 'Content-Type': 'application/json', Connection: 'close', ...extra };
    const token = getToken();
    if (token) h.Authorization = `Bearer ${token}`;
    return h;
}

function pickTransport(url) {
    return url.protocol === 'https:' ? https : http;
}

function defaultPort(url) {
    if (url.port) return parseInt(url.port, 10);
    return url.protocol === 'https:' ? 443 : 80;
}

/** GET JSON */
function httpGet(fullUrl) {
    return new Promise((resolve) => {
        try {
            const url = new URL(fullUrl);
            const mod = pickTransport(url);
            const req = mod.request(
                {
                    hostname: url.hostname,
                    port: defaultPort(url),
                    path: url.pathname + url.search,
                    method: 'GET',
                    headers: authHeaders(),
                    timeout: 12000,
                },
                (res) => {
                    let data = '';
                    res.on('data', (c) => (data += c));
                    res.on('end', () => {
                        try {
                            resolve(JSON.parse(data));
                        } catch {
                            resolve({ ok: false, error: data.slice(0, 200) });
                        }
                    });
                },
            );
            req.on('error', (e) => resolve({ ok: false, error: e.message }));
            req.on('timeout', () => {
                req.destroy();
                resolve({ ok: false, error: 'Timeout' });
            });
            req.end();
        } catch (e) {
            resolve({ ok: false, error: String(e.message || e) });
        }
    });
}

/** POST JSON → object */
function httpPost(apiPath, body, timeoutMs = 120000) {
    return new Promise((resolve) => {
        try {
            const data = JSON.stringify(body);
            const url = new URL(apiBase() + apiPath);
            const mod = pickTransport(url);
            const req = mod.request(
                {
                    hostname: url.hostname,
                    port: defaultPort(url),
                    path: url.pathname + url.search,
                    method: 'POST',
                    headers: {
                        ...authHeaders(),
                        'Content-Length': Buffer.byteLength(data),
                    },
                    timeout: timeoutMs,
                },
                (res) => {
                    let out = '';
                    res.on('data', (c) => (out += c));
                    res.on('end', () => {
                        try {
                            const parsed = JSON.parse(out);
                            if (res.statusCode === 401) {
                                resolve({ ok: false, error: 'Нужен вход — задай aiHelper.password или token' });
                            } else {
                                resolve(parsed);
                            }
                        } catch {
                            resolve({ ok: false, error: out.slice(0, 300) || `HTTP ${res.statusCode}` });
                        }
                    });
                },
            );
            req.on('error', (e) => resolve({ ok: false, error: e.message }));
            req.on('timeout', () => {
                req.destroy();
                resolve({ ok: false, error: 'Timeout' });
            });
            req.write(data);
            req.end();
        } catch (e) {
            resolve({ ok: false, error: String(e.message || e) });
        }
    });
}

/** SSE stream */
function httpStream(apiPath, body, { onText, onTool, onToolResult, onDone, onError }) {
    const data = JSON.stringify(body);
    const url = new URL(apiBase() + apiPath);
    const mod = pickTransport(url);
    let buf = '';
    let finished = false;

    function finish() {
        if (!finished) {
            finished = true;
            onDone && onDone();
        }
    }

    const req = mod.request(
        {
            hostname: url.hostname,
            port: defaultPort(url),
            path: url.pathname + url.search,
            method: 'POST',
            headers: {
                ...authHeaders(),
                'Content-Length': Buffer.byteLength(data),
            },
            timeout: 300000,
        },
        (res) => {
            if (res.statusCode === 401) {
                onError && onError('Нужен вход — Settings → aiHelper.password или token');
                finish();
                return;
            }
            res.setEncoding('utf8');
            res.on('data', (chunk) => {
                buf += chunk;
                const parts = buf.split('\n\n');
                buf = parts.pop();
                for (const part of parts) {
                    for (const line of part.split('\n')) {
                        if (!line.startsWith('data: ')) continue;
                        try {
                            const ev = JSON.parse(line.slice(6));
                            if (ev.type === 'text') onText && onText(ev.content);
                            else if (ev.type === 'tool_call') onTool && onTool(ev.name, ev.args);
                            else if (ev.type === 'tool_result')
                                onToolResult && onToolResult(ev.name, ev.result);
                            else if (ev.type === 'error') onError && onError(ev.content);
                            else if (ev.type === 'done') finish();
                        } catch (_) {}
                    }
                }
            });
            res.on('end', finish);
            res.on('error', (e) => {
                onError && onError(e.message);
                finish();
            });
        },
    );

    req.on('error', (e) => {
        onError &&
            onError(
                e.code === 'ECONNREFUSED'
                    ? `Нет связи с API ${apiBase()} — проверь aiHelper.apiUrl`
                    : e.message,
            );
        finish();
    });
    req.on('timeout', () => {
        req.destroy();
        onError && onError('Timeout (5 мин)');
        finish();
    });
    req.write(data);
    req.end();
    return req;
}

async function ensureToken() {
    if (getToken()) return getToken();
    const password = String(cfg().get('password', '') || '').trim();
    if (!password) return '';
    const r = await httpPost('/auth/login', { password });
    if (r.ok && r.token) {
        await cfg().update('token', r.token, vscode.ConfigurationTarget.Global);
        return r.token;
    }
    return '';
}

// ── Path mapping: local file → site-relative path ─────────────────────────────

function localRootUri() {
    const custom = String(cfg().get('localRoot', '') || '').trim();
    if (custom) return vscode.Uri.file(custom);
    const folder = vscode.workspace.workspaceFolders?.[0]?.uri;
    return folder || null;
}

function relativeForSite(doc) {
    const root = localRootUri();
    if (!root) return path.basename(doc.fileName);
    const rel = path.relative(root.fsPath, doc.fileName);
    if (!rel || rel.startsWith('..') || path.isAbsolute(rel)) {
        return path.basename(doc.fileName);
    }
    return rel.split(path.sep).join('/');
}

async function syncDocumentToSite(doc, { silent = false } = {}) {
    const site = getSite();
    if (!site) {
        if (!silent) {
            vscode.window.showWarningMessage('AI Helper: выбери сайт (команда «Выбрать сайт на VPS»)');
        }
        return { ok: false, error: 'site not set' };
    }
    if (doc.isUntitled || doc.uri.scheme !== 'file') {
        return { ok: false, error: 'untitled' };
    }
    await ensureToken();
    const rel = relativeForSite(doc);
    const content = doc.getText();
    const r = await httpPost('/sites/sync', { site, path: rel, content });
    if (r.ok) {
        if (!silent) {
            vscode.window.setStatusBarMessage(
                `$(cloud-upload) ${site}/${rel} → сайт (${r.bytes || '?'} B)`,
                3000,
            );
        }
        return r;
    }
    if (!silent) {
        vscode.window.showErrorMessage(`Синк на сайт: ${r.error || '?'}`);
    }
    return r;
}

function extractCodeBlocks(text) {
    const blocks = [];
    const re = /```(\w*)\n([\s\S]*?)```/g;
    let m;
    while ((m = re.exec(text)) !== null) {
        blocks.push({ lang: m[1] || 'text', code: m[2].trimEnd() });
    }
    return blocks;
}

// ── Sidebar ───────────────────────────────────────────────────────────────────

class ChatViewProvider {
    static viewType = 'aiHelper.chat';

    constructor(context) {
        this._ctx = context;
        this._view = null;
        this._lastCodeBlocks = [];
        this._currentRequest = null;
    }

    resolveWebviewView(webviewView) {
        this._view = webviewView;
        webviewView.webview.options = { enableScripts: true };
        webviewView.webview.html = this._html();
        webviewView.webview.onDidReceiveMessage((msg) => {
            switch (msg.type) {
                case 'send':
                    this._onSend(msg.text, msg.includeFile);
                    break;
                case 'applyCode':
                    this._applyCode(msg.code, msg.lang, msg.action);
                    break;
                case 'applyToSite':
                    this._applyToSite(msg.code, msg.relPath);
                    break;
                case 'cancelStream':
                    this._cancelStream();
                    break;
                case 'clearChat':
                    this._post({ type: 'clearChat' });
                    break;
                case 'openBrowser':
                    vscode.env.openExternal(vscode.Uri.parse(chatBase()));
                    break;
                case 'smartCommit':
                    this._smartCommit(msg.push || false);
                    break;
                case 'getStatus':
                    this._sendStatus();
                    break;
                case 'setSite':
                    cfg()
                        .update('site', msg.site || '', vscode.ConfigurationTarget.Global)
                        .then(() => this._sendStatus());
                    break;
                case 'toggleSync':
                    cfg()
                        .update('autoSyncOnSave', !!msg.on, vscode.ConfigurationTarget.Global)
                        .then(() => this._sendStatus());
                    break;
                case 'pushFile':
                    vscode.commands.executeCommand('aiHelper.pushFile');
                    break;
                case 'selectSite':
                    vscode.commands.executeCommand('aiHelper.selectSite');
                    break;
            }
        });
        this._sendStatus();
    }

    async _onSend(userText, includeFile) {
        if (!userText.trim()) return;
        await ensureToken();

        const editor = vscode.window.activeTextEditor;
        let filePart = '';
        let fileName = '';
        let relHint = '';

        if (includeFile && editor) {
            const doc = editor.document;
            const sel = editor.selection;
            const code = doc.getText(sel.isEmpty ? undefined : sel);
            const lang = doc.languageId;
            fileName = path.basename(doc.fileName);
            relHint = relativeForSite(doc);
            const preview = code.slice(0, 6000) + (code.length > 6000 ? '\n...[обрезано]' : '');
            filePart =
                `[Файл на сайте: ${relHint} | локально: ${fileName}]\n\`\`\`${lang}\n${preview}\n\`\`\`\n\n`;
        }

        const site = getSite();
        const fullMsg =
            filePart +
            userText +
            (site
                ? `\n\n[Служебно: правь файлы сайта «${site}» на сервере инструментами. Изменения сразу на сайте.]`
                : '\n\n[Служебно: сайт не выбран — только совет. Для правок на VPS выбери сайт.]');

        this._cancelStream();
        this._post({ type: 'userMessage', text: userText, file: fileName });
        this._post({ type: 'startResponse' });

        let responseText = '';
        this._currentRequest = httpStream(
            '/chat/stream',
            {
                message: fullMsg,
                ...(site ? { site } : {}),
            },
            {
                onText: (text) => {
                    responseText += text;
                    this._post({ type: 'appendText', text });
                },
                onTool: (name, args) => {
                    const label = `→ ${name}(${JSON.stringify(args || {}).slice(0, 80)})`;
                    this._post({ type: 'toolCall', label });
                },
                onToolResult: (name, result) => {
                    const ok = result && result.ok !== false;
                    const p = (result && (result.path || result.relative)) || '';
                    const mark = result && result.edited ? '✎' : ok ? '✓' : '✗';
                    this._post({
                        type: 'toolCall',
                        label: `${mark} ${name}${p ? ' → ' + p : ''}${ok ? ' (на сайте)' : ''}`,
                    });
                },
                onDone: () => {
                    this._currentRequest = null;
                    this._lastCodeBlocks = extractCodeBlocks(responseText);
                    this._post({
                        type: 'endResponse',
                        codeBlocks: this._lastCodeBlocks,
                        hasEditor: !!vscode.window.activeTextEditor,
                        site: getSite(),
                        relPath: relHint || '',
                    });
                },
                onError: (err) => {
                    this._currentRequest = null;
                    this._post({ type: 'responseError', error: err });
                },
            },
        );
    }

    _applyCode(code, lang, action) {
        const editor = vscode.window.activeTextEditor;
        if (!editor) {
            vscode.workspace
                .openTextDocument({ content: code, language: lang || 'plaintext' })
                .then((doc) => vscode.window.showTextDocument(doc));
            return;
        }
        editor
            .edit((b) => {
                if (action === 'replace_selection' && !editor.selection.isEmpty) {
                    b.replace(editor.selection, code);
                } else if (action === 'replace_all') {
                    const last = editor.document.lineAt(editor.document.lineCount - 1);
                    b.replace(new vscode.Range(new vscode.Position(0, 0), last.range.end), code);
                } else {
                    b.insert(editor.selection.active, '\n' + code + '\n');
                }
            })
            .then((ok) => {
                if (ok) vscode.window.showInformationMessage('✓ Код в редакторе');
            });
    }

    async _applyToSite(code, relPath) {
        const site = getSite();
        if (!site) {
            vscode.window.showWarningMessage('Сначала выбери сайт');
            return;
        }
        await ensureToken();
        let rel = (relPath || '').trim();
        if (!rel) {
            rel = await vscode.window.showInputBox({
                prompt: 'Путь файла на сайте (относительно корня сайта)',
                value: 'index.html',
            });
        }
        if (!rel) return;
        const r = await httpPost('/sites/sync', { site, path: rel, content: code });
        if (r.ok) {
            vscode.window.showInformationMessage(`✓ На сайте: ${site}/${rel}`);
            this._post({ type: 'toolCall', label: `✎ sync → ${site}/${rel}` });
        } else {
            vscode.window.showErrorMessage(r.error || 'Ошибка записи на сайт');
        }
    }

    async _smartCommit(push) {
        this._post({
            type: 'userMessage',
            text: push ? '🔀 Smart commit + push...' : '🔀 Smart commit...',
        });
        this._post({ type: 'startResponse' });
        const folder = vscode.workspace.workspaceFolders?.[0]?.uri?.fsPath || '';
        const r = await httpPost('/smart-commit', { push, project: folder });
        if (r.ok) {
            const detail = push && r.pushed ? ' + pushed ✓' : '';
            this._post({ type: 'appendText', text: `✓ Committed: \`${r.message}\`${detail}` });
        } else {
            this._post({ type: 'appendText', text: `✗ Ошибка: ${r.error || r.output || '?'}` });
        }
        this._post({ type: 'endResponse', codeBlocks: [], hasEditor: false, site: getSite() });
    }

    async _sendStatus() {
        await ensureToken();
        const r = await httpGet(apiBase() + '/status');
        let sites = [];
        if (r.ok) {
            const s = await httpGet(apiBase() + '/sites');
            sites = (s.sites || []).map((x) => x.name);
        }
        this._post({
            type: 'status',
            online: r.ok === true,
            ollama: r.ollama,
            groq: r.groq,
            deepseek: r.deepseek,
            free: r.free_llm,
            freeTools: r.free_tools === true,
            model: r.free_model || r.llm_model || '?',
            site: getSite(),
            sites,
            autoSync: autoSyncOn(),
            version: r.version,
        });
    }

    _cancelStream() {
        if (this._currentRequest) {
            try {
                this._currentRequest.destroy();
            } catch (_) {}
            this._currentRequest = null;
            this._post({ type: 'cancelledStream' });
        }
    }

    _post(msg) {
        if (this._view?.webview) this._view.webview.postMessage(msg);
    }

    _html() {
        return /* html */ `<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: var(--vscode-font-family);
  font-size: var(--vscode-font-size);
  background: var(--vscode-editor-background);
  color: var(--vscode-editor-foreground);
  display: flex; flex-direction: column; height: 100vh; overflow: hidden;
}
#statusBar {
  padding: 4px 8px; font-size: 11px; display: flex; gap: 8px; align-items: center;
  background: var(--vscode-statusBar-background, #007acc);
  color: var(--vscode-statusBar-foreground, #fff); flex-shrink: 0;
}
#statusBar .dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
#statusBar .dot.on { background: #4ec9b0; }
#statusBar .dot.off { background: #f44747; }
#statusBar a { color: inherit; text-decoration: none; cursor: pointer; font-size: 11px; }
#siteBar {
  padding: 6px 8px; display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
  border-bottom: 1px solid var(--vscode-input-border, #555); font-size: 11px; flex-shrink: 0;
}
#siteBar select, #siteBar button {
  background: var(--vscode-input-background); color: var(--vscode-input-foreground);
  border: 1px solid var(--vscode-input-border, #555); border-radius: 4px; padding: 3px 6px; font-size: 11px;
}
#siteBar button { cursor: pointer; }
#messages { flex: 1; overflow-y: auto; padding: 8px; display: flex; flex-direction: column; gap: 10px; }
.msg-user .bubble {
  background: var(--vscode-button-background); color: var(--vscode-button-foreground);
  border-radius: 10px 10px 2px 10px; padding: 7px 10px; align-self: flex-end; max-width: 90%;
  font-size: 12px; line-height: 1.5; white-space: pre-wrap; word-break: break-word;
}
.msg-user .file-tag { font-size: 10px; opacity: .7; align-self: flex-end; }
.msg-ai .bubble {
  background: var(--vscode-input-background); border: 1px solid var(--vscode-input-border, #555);
  border-radius: 2px 10px 10px 10px; padding: 7px 10px; max-width: 100%;
  font-size: 12px; line-height: 1.6; white-space: pre-wrap; word-break: break-word;
}
.msg-ai .bubble.streaming::after { content: '▌'; animation: blink .8s step-end infinite; }
@keyframes blink { 50% { opacity: 0; } }
.code-block { margin: 6px 0; border: 1px solid var(--vscode-editorGroup-border, #444); border-radius: 4px; overflow: hidden; }
.code-header {
  display: flex; justify-content: space-between; align-items: center;
  background: var(--vscode-editorGroupHeader-tabsBackground, #252526); padding: 3px 8px; font-size: 11px;
}
.code-header .actions { display: flex; gap: 4px; flex-wrap: wrap; }
.code-header button {
  background: var(--vscode-button-secondaryBackground, #3a3d41); color: var(--vscode-button-secondaryForeground, #ccc);
  border: none; border-radius: 3px; padding: 2px 6px; font-size: 10px; cursor: pointer;
}
.code-body {
  background: var(--vscode-textCodeBlock-background, #1e1e1e); padding: 8px; overflow-x: auto;
  font-family: var(--vscode-editor-font-family, monospace); font-size: 11px; white-space: pre;
}
.tool-call {
  font-size: 10px; color: var(--vscode-textLink-foreground, #4fc1ff); font-family: monospace;
  padding: 1px 4px; background: var(--vscode-textCodeBlock-background, #1e1e1e);
  border-radius: 3px; margin: 2px 0; display: inline-block;
}
.msg-error .bubble {
  background: var(--vscode-inputValidation-errorBackground, #5a1d1d);
  border: 1px solid var(--vscode-inputValidation-errorBorder, #be1100);
  border-radius: 4px; padding: 7px 10px; font-size: 12px; white-space: pre-wrap;
}
#inputArea { border-top: 1px solid var(--vscode-input-border, #555); padding: 8px; flex-shrink: 0; }
#hints { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 6px; }
.hint-chip {
  font-size: 10px; background: var(--vscode-badge-background, #4d4d4d); color: var(--vscode-badge-foreground, #ccc);
  border: none; border-radius: 10px; padding: 2px 7px; cursor: pointer;
}
#fileToggle { display: flex; align-items: center; gap: 5px; margin-bottom: 5px; font-size: 11px; cursor: pointer; }
#inputRow { display: flex; gap: 5px; align-items: flex-end; }
#inputBox {
  flex: 1; background: var(--vscode-input-background); border: 1px solid var(--vscode-input-border, #555);
  color: var(--vscode-input-foreground); border-radius: 6px; padding: 7px 10px; font-size: 12px;
  resize: none; min-height: 36px; max-height: 120px; outline: none;
}
#sendBtn, #cancelBtn {
  border: none; border-radius: 6px; width: 32px; height: 32px; cursor: pointer; font-size: 16px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
#sendBtn { background: var(--vscode-button-background); color: var(--vscode-button-foreground); }
#cancelBtn { background: var(--vscode-button-secondaryBackground, #3a3d41); color: var(--vscode-button-secondaryForeground, #ccc); display: none; }
#cancelBtn.visible { display: flex; }
#empty {
  flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 10px; color: var(--vscode-descriptionForeground, #888); text-align: center; padding: 20px; font-size: 12px;
}
#empty.hidden { display: none; }
</style>
</head>
<body>
<div id="statusBar">
  <span class="dot off" id="statusDot"></span>
  <span id="statusText">Подключение...</span>
  <span style="flex:1"></span>
  <a onclick="clearChat()" title="Очистить">⊘</a>
  <a onclick="openBrowser()" title="Панель">⬡</a>
</div>
<div id="siteBar">
  <span>Сайт</span>
  <select id="siteSelect" onchange="onSiteChange()"></select>
  <label style="display:flex;gap:4px;align-items:center;cursor:pointer">
    <input type="checkbox" id="syncCheck" onchange="onSyncToggle()"> авто-синк
  </label>
  <button onclick="vscode.postMessage({type:'pushFile'})" title="Ctrl+Alt+S">⬆ файл</button>
</div>
<div id="messages">
  <div id="empty">
    <div><strong>Правки сразу на сайте</strong></div>
    <div>Ctrl+Shift+P → «AI Helper: Настройка VPS»<br>
    (пароль, сайт, авто-синк). Потом пиши задачу или Ctrl+S.</div>
  </div>
</div>
<div id="inputArea">
  <div id="hints">
    <button class="hint-chip" onclick="useHint('Что скажешь о сайте? Разбери по файлам на сервере.')">О сайте</button>
    <button class="hint-chip" onclick="useHint('Поменяй заголовок в index.html / главной на более заметный')">Правка</button>
    <button class="hint-chip" onclick="useHint('Проверь WordPress siteurl/home и скажи что чинить')">WP</button>
    <button class="hint-chip" onclick="useHint('Выставь права 755/644 на сайте')">Права</button>
    <button class="hint-chip" onclick="smartCommit()">Commit</button>
  </div>
  <label id="fileToggle" for="fileCheck">
    <input type="checkbox" id="fileCheck" checked>
    <span>📎</span><span id="fileLabel">текущий файл в контекст</span>
  </label>
  <div id="inputRow">
    <textarea id="inputBox" placeholder="Что сделать на сайте..." rows="1"></textarea>
    <button id="cancelBtn" onclick="cancelStream()">⏹</button>
    <button id="sendBtn" onclick="sendMessage()">➤</button>
  </div>
</div>
<script>
const vscode = acquireVsCodeApi();
let streaming = false, currentBubble = null, currentTextNode = null, currentSite = '', currentRel = '';

function $(id) { return document.getElementById(id); }
function scrollBottom() { const m = $('messages'); m.scrollTop = m.scrollHeight; }
function setStreaming(on) {
  streaming = on;
  $('sendBtn').disabled = on;
  $('cancelBtn').classList.toggle('visible', on);
  if (currentBubble) currentBubble.classList.toggle('streaming', on);
}
function useHint(text) { $('inputBox').value = text; $('inputBox').focus(); }
function smartCommit() { vscode.postMessage({ type: 'smartCommit', push: false }); }
function cancelStream() { vscode.postMessage({ type: 'cancelStream' }); setStreaming(false); }
function clearChat() {
  $('messages').innerHTML = '';
  $('empty').classList.remove('hidden');
  vscode.postMessage({ type: 'clearChat' });
}
function openBrowser() { vscode.postMessage({ type: 'openBrowser' }); }
function onSiteChange() { vscode.postMessage({ type: 'setSite', site: $('siteSelect').value }); }
function onSyncToggle() { vscode.postMessage({ type: 'toggleSync', on: $('syncCheck').checked }); }

$('inputBox').addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});
window.addEventListener('message', msg => handleMessage(msg.data));
vscode.postMessage({ type: 'getStatus' });

function sendMessage() {
  const text = $('inputBox').value.trim();
  if (!text || streaming) return;
  vscode.postMessage({ type: 'send', text, includeFile: $('fileCheck').checked });
  $('inputBox').value = '';
}

function addUserMessage(text, fileName) {
  $('empty').classList.add('hidden');
  const div = document.createElement('div');
  div.className = 'msg msg-user';
  if (fileName) {
    const tag = document.createElement('div');
    tag.className = 'file-tag';
    tag.textContent = '📎 ' + fileName;
    div.appendChild(tag);
  }
  const bub = document.createElement('div');
  bub.className = 'bubble';
  bub.textContent = text;
  div.appendChild(bub);
  $('messages').appendChild(div);
  scrollBottom();
}
function startAIMessage() {
  $('empty').classList.add('hidden');
  const div = document.createElement('div');
  div.className = 'msg msg-ai';
  const bub = document.createElement('div');
  bub.className = 'bubble streaming';
  div.appendChild(bub);
  $('messages').appendChild(div);
  currentBubble = bub;
  currentTextNode = document.createTextNode('');
  bub.appendChild(currentTextNode);
  setStreaming(true);
  scrollBottom();
}
function appendText(text) {
  if (!currentTextNode) return;
  currentTextNode.textContent += text;
  scrollBottom();
}
function addToolCall(label) {
  if (!currentBubble) return;
  const span = document.createElement('span');
  span.className = 'tool-call';
  span.textContent = label;
  currentBubble.appendChild(document.createElement('br'));
  currentBubble.appendChild(span);
  currentBubble.appendChild(document.createElement('br'));
  scrollBottom();
}
function finalizeMessage(blocks, hasEditor, site, relPath) {
  setStreaming(false);
  if (!currentBubble) return;
  currentSite = site || '';
  currentRel = relPath || '';
  const rawText = currentTextNode ? currentTextNode.textContent : '';
  currentBubble.innerHTML = '';
  const parts = rawText.split(/\`\`\`(\\w*)\\n([\\s\\S]*?)\`\`\`/g);
  let i = 0;
  while (i < parts.length) {
    if (i % 4 === 0) {
      if (parts[i]) {
        const t = document.createElement('span');
        t.style.whiteSpace = 'pre-wrap';
        t.textContent = parts[i];
        currentBubble.appendChild(t);
      }
    } else if (i % 4 === 1) {
      currentBubble.appendChild(makeCodeBlock(parts[i] || 'code', parts[i+1] || '', hasEditor));
      i += 2;
    }
    i++;
  }
  if (blocks && blocks.length && !rawText.includes('\`\`\`')) {
    for (const b of blocks) currentBubble.appendChild(makeCodeBlock(b.lang, b.code, hasEditor));
  }
  currentBubble = null;
  currentTextNode = null;
  scrollBottom();
}
function makeCodeBlock(lang, code, hasEditor) {
  const wrapper = document.createElement('div');
  wrapper.className = 'code-block';
  const header = document.createElement('div');
  header.className = 'code-header';
  const langSpan = document.createElement('span');
  langSpan.textContent = lang || 'code';
  header.appendChild(langSpan);
  const actions = document.createElement('div');
  actions.className = 'actions';
  const copyBtn = document.createElement('button');
  copyBtn.textContent = '📋';
  copyBtn.onclick = () => navigator.clipboard.writeText(code);
  actions.appendChild(copyBtn);
  if (currentSite) {
    const siteBtn = document.createElement('button');
    siteBtn.textContent = '☁ На сайт';
    siteBtn.onclick = () => vscode.postMessage({
      type: 'applyToSite', code, relPath: currentRel || ''
    });
    actions.appendChild(siteBtn);
  }
  if (hasEditor) {
    const insBtn = document.createElement('button');
    insBtn.textContent = '⬇ Вставить';
    insBtn.onclick = () => vscode.postMessage({ type: 'applyCode', code, lang, action: 'insert' });
    actions.appendChild(insBtn);
    const allBtn = document.createElement('button');
    allBtn.textContent = '⬆ Файл';
    allBtn.onclick = () => {
      if (confirm('Заменить весь локальный файл?')) {
        vscode.postMessage({ type: 'applyCode', code, lang, action: 'replace_all' });
      }
    };
    actions.appendChild(allBtn);
  }
  header.appendChild(actions);
  wrapper.appendChild(header);
  const body = document.createElement('pre');
  body.className = 'code-body';
  body.textContent = code;
  wrapper.appendChild(body);
  return wrapper;
}
function showError(text) {
  setStreaming(false);
  const div = document.createElement('div');
  div.className = 'msg msg-error';
  const bub = document.createElement('div');
  bub.className = 'bubble';
  let tip = String(text || '');
  if (/HTTP\s*400|Bad Request/i.test(tip) && /ollama/i.test(tip)) {
    tip += '\n\nПодсказка: модель 1.5b не принимает tools. Обнови API на VPS (bootstrap-update) — правки пойдут через DeepSeek/Groq, чат останется на Ollama.';
  }
  bub.textContent = '⚠ ' + tip;
  div.appendChild(bub);
  $('messages').appendChild(div);
  currentBubble = null;
  currentTextNode = null;
}
function updateStatus(msg) {
  const dot = $('statusDot');
  const text = $('statusText');
  if (msg.online) {
    dot.className = 'dot on';
    const cloud = (msg.deepseek || msg.groq) ? ' · cloud' : '';
    const tools = msg.freeTools ? '' : (msg.free ? ' · chat' : '');
    text.textContent = (msg.free ? '🆓 ' : '') + (msg.model || '?') + tools + cloud + (msg.version ? ' · v' + msg.version : '');
  } else {
    dot.className = 'dot off';
    text.textContent = 'API offline — проверь aiHelper.apiUrl';
  }
  const sel = $('siteSelect');
  const sites = msg.sites || [];
  sel.innerHTML = '<option value="">— сайт —</option>' +
    sites.map(s => '<option value="'+s+'"'+(s===msg.site?' selected':'')+'>'+s+'</option>').join('');
  if (msg.site && !sites.includes(msg.site)) {
    const o = document.createElement('option');
    o.value = msg.site; o.textContent = msg.site; o.selected = true;
    sel.appendChild(o);
  }
  $('syncCheck').checked = !!msg.autoSync;
  currentSite = msg.site || '';
}
function handleMessage(msg) {
  switch (msg.type) {
    case 'userMessage': addUserMessage(msg.text, msg.file); break;
    case 'startResponse': startAIMessage(); break;
    case 'appendText': appendText(msg.text); break;
    case 'toolCall': addToolCall(msg.label); break;
    case 'endResponse': finalizeMessage(msg.codeBlocks, msg.hasEditor, msg.site, msg.relPath); break;
    case 'responseError': showError(msg.error); break;
    case 'cancelledStream': setStreaming(false); break;
    case 'status': updateStatus(msg); break;
    case 'clearChat':
      $('messages').innerHTML = '';
      $('empty').classList.remove('hidden');
      break;
    case 'activeFile':
      $('fileLabel').textContent = msg.name || 'текущий файл';
      break;
  }
}
</script>
</body>
</html>`;
    }
}

// ── Status bar ────────────────────────────────────────────────────────────────

let statusBar;

async function refreshStatusBar() {
    await ensureToken();
    const r = await httpGet(apiBase() + '/status');
    if (!statusBar) return;
    const site = getSite();
    if (r.ok) {
        statusBar.text = `$(hubot) AI ${site || '·'} ${autoSyncOn() ? '$(cloud-upload)' : ''}`;
        statusBar.tooltip = `API ${apiBase()}\nСайт: ${site || 'не выбран'}\nАвто-синк: ${autoSyncOn() ? 'ON' : 'OFF'}\nКлик → чат`;
        statusBar.command = 'aiHelper.chat.focus';
    } else {
        statusBar.text = '$(warning) AI Helper offline';
        statusBar.tooltip = r.error || 'Нет связи с API';
        statusBar.command = 'aiHelper.chat.focus';
    }
}

async function runSetupWizard() {
    const api = await vscode.window.showInputBox({
        prompt: 'API URL панели на VPS',
        value: apiBase() || 'http://80.78.248.195/api',
        ignoreFocusOut: true,
    });
    if (api === undefined) return;
    await cfg().update('apiUrl', api.trim(), vscode.ConfigurationTarget.Global);

    const password = await vscode.window.showInputBox({
        prompt: 'Пароль панели (PANEL_PASSWORD)',
        password: true,
        value: String(cfg().get('password', '') || ''),
        ignoreFocusOut: true,
    });
    if (password === undefined) return;
    await cfg().update('password', password, vscode.ConfigurationTarget.Global);

    await ensureToken();
    const s = await httpGet(apiBase() + '/sites');
    const names = (s.sites || []).map((x) => x.name);
    let site = getSite();
    if (names.length) {
        const pick = await vscode.window.showQuickPick(
            [{ label: '(не выбирать)', description: 'только чат без записи' }, ...names.map((n) => ({ label: n }))],
            { placeHolder: 'Какой сайт править на VPS?', ignoreFocusOut: true },
        );
        if (pick && pick.label !== '(не выбирать)') site = pick.label;
    } else {
        site = await vscode.window.showInputBox({
            prompt: 'Имя сайта на сервере (например 5mb2)',
            value: site || '5mb2',
            ignoreFocusOut: true,
        });
        if (site === undefined) return;
    }
    await cfg().update('site', site || '', vscode.ConfigurationTarget.Global);

    const syncPick = await vscode.window.showQuickPick(
        [
            { label: 'Да', description: 'Ctrl+S сразу на сайт', value: true },
            { label: 'Нет', description: 'только вручную Ctrl+Alt+S', value: false },
        ],
        { placeHolder: 'Авто-синк при сохранении?', ignoreFocusOut: true },
    );
    if (syncPick) {
        await cfg().update('autoSyncOnSave', !!syncPick.value, vscode.ConfigurationTarget.Global);
    }

    const st = await httpGet(apiBase() + '/status');
    if (st.ok) {
        vscode.window.showInformationMessage(
            `AI Helper готов → сайт «${getSite() || '—'}», авто-синк ${autoSyncOn() ? 'ON' : 'OFF'}`,
        );
    } else {
        vscode.window.showWarningMessage(
            `Настройки сохранены, но API не ответил: ${st.error || 'offline'}. Проверь apiUrl.`,
        );
    }
}

function activate(context) {
    const provider = new ChatViewProvider(context);
    context.subscriptions.push(
        vscode.window.registerWebviewViewProvider(ChatViewProvider.viewType, provider, {
            webviewOptions: { retainContextWhenHidden: true },
        }),
    );

    statusBar = vscode.window.createStatusBarItem(vscode.StatusBarAlignment.Right, 100);
    statusBar.show();
    context.subscriptions.push(statusBar);
    refreshStatusBar();
    const timer = setInterval(refreshStatusBar, 30_000);
    context.subscriptions.push({ dispose: () => clearInterval(timer) });

    // First run: offer setup if password/site missing (old extension only had apiUrl)
    setTimeout(() => {
        if (!getToken() && !String(cfg().get('password', '') || '').trim()) {
            vscode.window
                .showInformationMessage(
                    'AI Helper: нужны Password и Site. Сейчас только Api Url / Chat Url — значит стоит старая версия или не настроено.',
                    'Настроить',
                    'Позже',
                )
                .then((choice) => {
                    if (choice === 'Настроить') vscode.commands.executeCommand('aiHelper.setup');
                });
        }
    }, 1500);

    // Auto-sync on save → live site
    context.subscriptions.push(
        vscode.workspace.onDidSaveTextDocument(async (doc) => {
            if (!autoSyncOn() || !getSite()) return;
            if (doc.uri.scheme !== 'file') return;
            // skip node_modules / .git
            const fp = doc.fileName.replace(/\\/g, '/');
            if (fp.includes('/node_modules/') || fp.includes('/.git/')) return;
            await syncDocumentToSite(doc, { silent: true });
            refreshStatusBar();
        }),
    );

    vscode.window.onDidChangeActiveTextEditor(
        (editor) => {
            if (provider._view && editor) {
                provider._post({ type: 'activeFile', name: path.basename(editor.document.fileName) });
            }
        },
        null,
        context.subscriptions,
    );

    context.subscriptions.push(
        vscode.commands.registerCommand('aiHelper.setup', async () => {
            await runSetupWizard();
            provider._sendStatus();
            refreshStatusBar();
        }),
        vscode.commands.registerCommand('aiHelper.newChat', () => {
            vscode.commands.executeCommand('aiHelper.chat.focus');
        }),
        vscode.commands.registerCommand('aiHelper.selectSite', async () => {
            await ensureToken();
            const s = await httpGet(apiBase() + '/sites');
            const names = (s.sites || []).map((x) => x.name);
            if (!names.length) {
                vscode.window.showWarningMessage('Нет сайтов на сервере или нет доступа к API');
                return;
            }
            const pick = await vscode.window.showQuickPick(names, {
                placeHolder: 'Сайт на VPS (правки и синк сюда)',
            });
            if (pick) {
                await cfg().update('site', pick, vscode.ConfigurationTarget.Global);
                vscode.window.showInformationMessage(`AI Helper → сайт «${pick}»`);
                provider._sendStatus();
                refreshStatusBar();
            }
        }),
        vscode.commands.registerCommand('aiHelper.pushFile', async () => {
            const editor = vscode.window.activeTextEditor;
            if (!editor) {
                vscode.window.showWarningMessage('Нет открытого файла');
                return;
            }
            await ensureToken();
            const r = await syncDocumentToSite(editor.document, { silent: false });
            if (r.ok) provider._sendStatus();
        }),
        vscode.commands.registerCommand('aiHelper.toggleAutoSync', async () => {
            const next = !autoSyncOn();
            await cfg().update('autoSyncOnSave', next, vscode.ConfigurationTarget.Global);
            vscode.window.showInformationMessage(`Авто-синк на сайт: ${next ? 'ON' : 'OFF'}`);
            provider._sendStatus();
            refreshStatusBar();
        }),
        vscode.commands.registerCommand('aiHelper.smartCommit', async () => {
            const folder = vscode.workspace.workspaceFolders?.[0]?.uri?.fsPath || '';
            const r = await httpPost('/smart-commit', { push: false, project: folder });
            if (r.ok) vscode.window.showInformationMessage(`✓ Committed: ${r.message}`);
            else vscode.window.showErrorMessage(`Smart commit: ${r.error || r.output || '?'}`);
        }),
        vscode.commands.registerCommand('aiHelper.smartCommitPush', async () => {
            const folder = vscode.workspace.workspaceFolders?.[0]?.uri?.fsPath || '';
            const r = await httpPost('/smart-commit', { push: true, project: folder });
            if (r.ok)
                vscode.window.showInformationMessage(
                    `✓ Committed${r.pushed ? ' + pushed' : ''}: ${r.message}`,
                );
            else vscode.window.showErrorMessage(`Smart commit+push: ${r.error || '?'}`);
        }),
        vscode.commands.registerCommand('aiHelper.applyLastCode', () => {
            vscode.commands.executeCommand('aiHelper.chat.focus');
        }),
    );
}

function deactivate() {}

module.exports = { activate, deactivate };
