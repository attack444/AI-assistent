/**
 * AI Helper VS Code Extension
 *
 * Sidebar chat panel with streaming responses.
 * Can apply code directly to the active editor.
 * Calls AI Helper API on localhost:8502.
 */

const vscode = require('vscode');
const http   = require('http');
const path   = require('path');

// ── Helpers ───────────────────────────────────────────────────────────────────

function apiBase() {
    return vscode.workspace.getConfiguration('aiHelper').get('apiUrl', 'http://localhost:8502');
}
function chatBase() {
    return vscode.workspace.getConfiguration('aiHelper').get('chatUrl', 'http://localhost:8501');
}

/** Simple HTTP GET → Promise<object> */
function httpGet(url) {
    return new Promise((resolve) => {
        const req = http.get(url, { timeout: 8000 }, res => {
            let data = '';
            res.on('data', c => data += c);
            res.on('end',  () => { try { resolve(JSON.parse(data)); } catch { resolve({}); } });
        });
        req.on('error',   () => resolve({ ok: false }));
        req.on('timeout', () => { req.destroy(); resolve({ ok: false }); });
    });
}

/** POST JSON, returns Promise<object> */
function httpPost(path_, body, timeoutMs = 300000) {
    return new Promise((resolve) => {
        const data = JSON.stringify(body);
        const url  = new URL(apiBase());
        const req  = http.request({
            hostname: url.hostname,
            port:     parseInt(url.port) || 80,
            path:     path_,
            method:   'POST',
            headers:  {
                'Content-Type':   'application/json',
                'Content-Length': Buffer.byteLength(data),
                'Connection':     'close',
            },
            timeout: timeoutMs,
        }, res => {
            let out = '';
            res.on('data', c => out += c);
            res.on('end', () => { try { resolve(JSON.parse(out)); } catch { resolve({ ok: false, response: out }); } });
        });
        req.on('error',   e => resolve({ ok: false, error: e.message }));
        req.on('timeout', () => { req.destroy(); resolve({ ok: false, error: 'Timeout' }); });
        req.write(data);
        req.end();
    });
}

/** POST JSON, streams SSE events, calls callbacks */
function httpStream(path_, body, { onText, onTool, onDone, onError }) {
    const data     = JSON.stringify(body);
    const url      = new URL(apiBase());
    let   buf      = '';
    let   finished = false;  // guard: call onDone only once

    function finish() {
        if (!finished) { finished = true; onDone && onDone(); }
    }

    const req = http.request({
        hostname: url.hostname,
        port:     parseInt(url.port) || 80,
        path:     path_,
        method:   'POST',
        headers:  {
            'Content-Type':   'application/json',
            'Content-Length': Buffer.byteLength(data),
            'Connection':     'close',   // tell server to close after response
        },
        timeout: 300000,
    }, res => {
        res.setEncoding('utf8');

        res.on('data', chunk => {
            buf += chunk;
            // SSE events separated by blank lines (\n\n)
            const parts = buf.split('\n\n');
            buf = parts.pop();   // keep possibly-incomplete last part
            for (const part of parts) {
                for (const line of part.split('\n')) {
                    if (!line.startsWith('data: ')) continue;
                    try {
                        const ev = JSON.parse(line.slice(6));
                        if      (ev.type === 'text')      onText && onText(ev.content);
                        else if (ev.type === 'tool_call') onTool && onTool(ev.name, ev.args);
                        else if (ev.type === 'error')     { onError && onError(ev.content); }
                        else if (ev.type === 'done')      finish();
                    } catch (_) {}
                }
            }
        });

        res.on('end', finish);
        res.on('error', e => { onError && onError(e.message); finish(); });
    });

    req.on('error',   e  => { onError && onError(
        e.code === 'ECONNREFUSED'
            ? 'AI Helper не запущен. Открой START.bat'
            : e.message
    ); finish(); });
    req.on('timeout', () => { req.destroy(); onError && onError('Timeout (5 мин)'); finish(); });

    req.write(data);
    req.end();
    return req;
}

// ── Extract code blocks from markdown ────────────────────────────────────────

function extractCodeBlocks(text) {
    const blocks = [];
    const re = /```(\w*)\n([\s\S]*?)```/g;
    let m;
    while ((m = re.exec(text)) !== null) {
        blocks.push({ lang: m[1] || 'text', code: m[2].trimEnd() });
    }
    return blocks;
}

// ── Sidebar WebView Provider ──────────────────────────────────────────────────

class ChatViewProvider {
    static viewType = 'aiHelper.chat';

    constructor(context) {
        this._ctx  = context;
        this._view = null;
        this._lastCodeBlocks = [];
        this._currentRequest = null;
    }

    resolveWebviewView(webviewView) {
        this._view = webviewView;
        webviewView.webview.options = { enableScripts: true };
        webviewView.webview.html    = this._html();

        // Messages from webview → extension
        webviewView.webview.onDidReceiveMessage(msg => {
            switch (msg.type) {
                case 'send':         this._onSend(msg.text, msg.includeFile); break;
                case 'applyCode':    this._applyCode(msg.code, msg.lang, msg.action); break;
                case 'cancelStream': this._cancelStream(); break;
                case 'clearChat':    this._post({ type: 'clearChat' }); break;
                case 'openBrowser':  vscode.env.openExternal(vscode.Uri.parse(chatBase())); break;
                case 'smartCommit':  this._smartCommit(msg.push || false); break;
                case 'getStatus':    this._sendStatus(); break;
            }
        });

        // Send initial status
        this._sendStatus();
    }

    // ── Handle chat message ───────────────────────────────────────────────────

    _onSend(userText, includeFile) {
        if (!userText.trim()) return;

        const editor    = vscode.window.activeTextEditor;
        let   filePart  = '';
        let   fileName  = '';

        if (includeFile && editor) {
            const doc  = editor.document;
            const sel  = editor.selection;
            const code = doc.getText(sel.isEmpty ? undefined : sel);
            const lang = doc.languageId;
            fileName   = path.basename(doc.fileName);
            const preview = code.slice(0, 6000) + (code.length > 6000 ? '\n...[обрезано]' : '');
            filePart = `[Файл: ${fileName}]\n\`\`\`${lang}\n${preview}\n\`\`\`\n\n`;
        }

        const fullMsg = filePart + userText;

        // Cancel previous request FIRST (before showing new loading state)
        this._cancelStream();

        // Show user bubble + start loading indicator
        this._post({ type: 'userMessage', text: userText, file: fileName });

        let responseText = '';
        this._post({ type: 'startResponse' });

        this._currentRequest = httpStream('/chat/stream', { message: fullMsg }, {
            onText: text => {
                responseText += text;
                this._post({ type: 'appendText', text });
            },
            onTool: (name, args) => {
                const label = `→ ${name}(${JSON.stringify(args || {}).slice(0, 60)})`;
                this._post({ type: 'toolCall', label });
            },
            onDone: () => {
                this._currentRequest = null;
                this._lastCodeBlocks = extractCodeBlocks(responseText);
                this._post({
                    type:       'endResponse',
                    codeBlocks: this._lastCodeBlocks,
                    hasEditor:  !!vscode.window.activeTextEditor,
                });
            },
            onError: err => {
                this._currentRequest = null;
                this._post({ type: 'responseError', error: err });
            },
        });
    }

    // ── Apply code to editor ──────────────────────────────────────────────────

    _applyCode(code, lang, action) {
        const editor = vscode.window.activeTextEditor;
        if (!editor) {
            // Open untitled doc with the code
            vscode.workspace.openTextDocument({ content: code, language: lang || 'plaintext' })
                .then(doc => vscode.window.showTextDocument(doc));
            return;
        }

        editor.edit(b => {
            if (action === 'replace_selection' && !editor.selection.isEmpty) {
                b.replace(editor.selection, code);
            } else if (action === 'replace_all') {
                const last = editor.document.lineAt(editor.document.lineCount - 1);
                b.replace(new vscode.Range(new vscode.Position(0, 0), last.range.end), code);
            } else {
                // insert at cursor
                b.insert(editor.selection.active, '\n' + code + '\n');
            }
        }).then(ok => {
            if (ok) {
                vscode.window.showInformationMessage('✓ Код применён');
            }
        });
    }

    // ── Smart commit ──────────────────────────────────────────────────────────

    async _smartCommit(push) {
        this._post({ type: 'userMessage', text: push ? '🔀 Smart commit + push...' : '🔀 Smart commit...' });
        this._post({ type: 'startResponse' });

        const folder = vscode.workspace.workspaceFolders?.[0]?.uri?.fsPath || '';
        const r = await httpPost('/smart-commit', { push, project: folder });

        if (r.ok) {
            const detail = push && r.pushed ? ' + pushed ✓' : '';
            this._post({ type: 'appendText', text: `✓ Committed: \`${r.message}\`${detail}` });
        } else {
            this._post({ type: 'appendText', text: `✗ Ошибка: ${r.error || r.output || '?'}` });
        }
        this._post({ type: 'endResponse', codeBlocks: [], hasEditor: false });
    }

    // ── Status ────────────────────────────────────────────────────────────────

    async _sendStatus() {
        const r = await httpGet(apiBase() + '/status');
        this._post({
            type:     'status',
            online:   r.ok === true,
            ollama:   r.ollama,
            groq:     r.groq,
            model:    r.llm_model || '?',
            projects: r.projects || [],
        });
    }

    _cancelStream() {
        if (this._currentRequest) {
            try { this._currentRequest.destroy(); } catch (_) {}
            this._currentRequest = null;
            this._post({ type: 'cancelledStream' });
        }
    }

    _post(msg) {
        if (this._view?.webview) {
            this._view.webview.postMessage(msg);
        }
    }

    // ── HTML ──────────────────────────────────────────────────────────────────

    _html() {
        return /* html */`<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--vscode-font-family);
  font-size: var(--vscode-font-size);
  background: var(--vscode-editor-background);
  color: var(--vscode-editor-foreground);
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
}

/* ── Status bar ── */
#statusBar {
  padding: 4px 8px;
  font-size: 11px;
  display: flex;
  gap: 8px;
  align-items: center;
  background: var(--vscode-statusBar-background, #007acc);
  color: var(--vscode-statusBar-foreground, #fff);
  flex-shrink: 0;
}
#statusBar .dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
#statusBar .dot.on  { background: #4ec9b0; }
#statusBar .dot.off { background: #f44747; }
#statusBar a { color: inherit; text-decoration: none; cursor: pointer; font-size: 11px; }
#statusBar a:hover { text-decoration: underline; }

/* ── Messages ── */
#messages {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
#messages::-webkit-scrollbar { width: 4px; }
#messages::-webkit-scrollbar-thumb { background: var(--vscode-scrollbarSlider-background); border-radius: 2px; }

.msg { display: flex; flex-direction: column; gap: 4px; }

.msg-user .bubble {
  background: var(--vscode-button-background);
  color: var(--vscode-button-foreground);
  border-radius: 10px 10px 2px 10px;
  padding: 7px 10px;
  align-self: flex-end;
  max-width: 90%;
  font-size: 12px;
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-word;
}
.msg-user .file-tag {
  font-size: 10px;
  opacity: .7;
  align-self: flex-end;
  margin-bottom: -2px;
}

.msg-ai .bubble {
  background: var(--vscode-input-background);
  border: 1px solid var(--vscode-input-border, #555);
  border-radius: 2px 10px 10px 10px;
  padding: 7px 10px;
  max-width: 100%;
  font-size: 12px;
  line-height: 1.6;
  white-space: pre-wrap;
  word-break: break-word;
}
.msg-ai .bubble.streaming::after {
  content: '▌';
  animation: blink .8s step-end infinite;
}
@keyframes blink { 50% { opacity: 0; } }

/* code blocks */
.code-block {
  margin: 6px 0;
  border: 1px solid var(--vscode-editorGroup-border, #444);
  border-radius: 4px;
  overflow: hidden;
}
.code-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--vscode-editorGroupHeader-tabsBackground, #252526);
  padding: 3px 8px;
  font-size: 11px;
  color: var(--vscode-tab-inactiveForeground, #aaa);
}
.code-header .lang { font-family: monospace; }
.code-header .actions { display: flex; gap: 4px; }
.code-header button {
  background: var(--vscode-button-secondaryBackground, #3a3d41);
  color: var(--vscode-button-secondaryForeground, #ccc);
  border: none;
  border-radius: 3px;
  padding: 2px 6px;
  font-size: 10px;
  cursor: pointer;
  transition: background .15s;
}
.code-header button:hover { background: var(--vscode-button-background); color: var(--vscode-button-foreground); }
.code-body {
  background: var(--vscode-textCodeBlock-background, #1e1e1e);
  padding: 8px;
  overflow-x: auto;
  font-family: var(--vscode-editor-font-family, monospace);
  font-size: 11px;
  line-height: 1.5;
  white-space: pre;
  tab-size: 4;
}

/* tool call */
.tool-call {
  font-size: 10px;
  color: var(--vscode-textLink-foreground, #4fc1ff);
  font-family: monospace;
  padding: 1px 4px;
  background: var(--vscode-textCodeBlock-background, #1e1e1e);
  border-radius: 3px;
  margin: 2px 0;
  display: inline-block;
}

/* error */
.msg-error .bubble {
  background: var(--vscode-inputValidation-errorBackground, #5a1d1d);
  border: 1px solid var(--vscode-inputValidation-errorBorder, #be1100);
  border-radius: 4px;
  padding: 7px 10px;
  font-size: 12px;
  white-space: pre-wrap;
  word-break: break-word;
}

/* ── Input area ── */
#inputArea {
  border-top: 1px solid var(--vscode-input-border, #555);
  padding: 8px;
  flex-shrink: 0;
  background: var(--vscode-editor-background);
}

#fileToggle {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 5px;
  font-size: 11px;
  color: var(--vscode-descriptionForeground, #aaa);
  cursor: pointer;
  user-select: none;
}
#fileToggle input[type=checkbox] { cursor: pointer; }
#fileLabel { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 160px; }

#inputRow { display: flex; gap: 5px; align-items: flex-end; }

#inputBox {
  flex: 1;
  background: var(--vscode-input-background);
  border: 1px solid var(--vscode-input-border, #555);
  color: var(--vscode-input-foreground);
  border-radius: 6px;
  padding: 7px 10px;
  font-family: inherit;
  font-size: 12px;
  resize: none;
  min-height: 36px;
  max-height: 120px;
  overflow-y: auto;
  line-height: 1.5;
  outline: none;
  transition: border-color .15s;
}
#inputBox:focus { border-color: var(--vscode-focusBorder, #007fd4); }
#inputBox::placeholder { color: var(--vscode-input-placeholderForeground, #aaa); }

#sendBtn {
  background: var(--vscode-button-background);
  color: var(--vscode-button-foreground);
  border: none;
  border-radius: 6px;
  width: 32px;
  height: 32px;
  cursor: pointer;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background .15s, opacity .15s;
}
#sendBtn:hover:not(:disabled) { filter: brightness(1.2); }
#sendBtn:disabled { opacity: .45; cursor: default; }

#cancelBtn {
  background: var(--vscode-button-secondaryBackground, #3a3d41);
  color: var(--vscode-button-secondaryForeground, #ccc);
  border: none;
  border-radius: 6px;
  width: 32px;
  height: 32px;
  cursor: pointer;
  font-size: 14px;
  display: none;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
#cancelBtn.visible { display: flex; }

/* hints */
#hints {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 6px;
}
.hint-chip {
  font-size: 10px;
  background: var(--vscode-badge-background, #4d4d4d);
  color: var(--vscode-badge-foreground, #ccc);
  border: none;
  border-radius: 10px;
  padding: 2px 7px;
  cursor: pointer;
  transition: background .15s;
}
.hint-chip:hover { background: var(--vscode-button-background); color: var(--vscode-button-foreground); }

/* empty state */
#empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: var(--vscode-descriptionForeground, #888);
  text-align: center;
  padding: 20px;
  font-size: 12px;
}
#empty.hidden { display: none; }
#empty svg { opacity: .4; }
</style>
</head>
<body>

<!-- Status bar -->
<div id="statusBar">
  <span class="dot off" id="statusDot"></span>
  <span id="statusText">Подключение...</span>
  <span style="flex:1"></span>
  <a onclick="clearChat()" title="Очистить чат">⊘</a>
  <a onclick="openBrowser()" title="Открыть в браузере">⬡</a>
</div>

<!-- Messages -->
<div id="messages">
  <div id="empty">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
    </svg>
    <div><strong>AI Helper готов</strong></div>
    <div>Включи файл ниже и задай вопрос<br>или просто напиши что нужно сделать</div>
  </div>
</div>

<!-- Input area -->
<div id="inputArea">
  <!-- Hint chips -->
  <div id="hints">
    <button class="hint-chip" onclick="useHint('Найди и исправь баги')">🔍 Баги</button>
    <button class="hint-chip" onclick="useHint('Объясни код')">📖 Объяснить</button>
    <button class="hint-chip" onclick="useHint('Напиши тесты pytest')">🧪 Тесты</button>
    <button class="hint-chip" onclick="useHint('Отрефактори код')">✨ Рефактор</button>
    <button class="hint-chip" onclick="smartCommit()">🔀 Commit</button>
  </div>

  <!-- File toggle -->
  <label id="fileToggle" for="fileCheck">
    <input type="checkbox" id="fileCheck" checked>
    <span>📎</span>
    <span id="fileLabel">включить текущий файл</span>
  </label>

  <!-- Input row -->
  <div id="inputRow">
    <textarea
      id="inputBox"
      placeholder="Напиши что нужно сделать..."
      rows="1"
    ></textarea>
    <button id="cancelBtn" onclick="cancelStream()" title="Остановить">⏹</button>
    <button id="sendBtn"   onclick="sendMessage()" title="Отправить (Enter)">➤</button>
  </div>
</div>

<script>
const vscode = acquireVsCodeApi();

// ── State ─────────────────────────────────────────────────────────────────────
let streaming    = false;
let currentBubble = null;
let currentTextNode = null;
let codeBlocks   = [];

// ── UI helpers ────────────────────────────────────────────────────────────────

function $(id) { return document.getElementById(id); }

function scrollBottom() {
    const m = $('messages');
    m.scrollTop = m.scrollHeight;
}

function setStreaming(on) {
    streaming = on;
    $('sendBtn').disabled  = on;
    $('cancelBtn').classList.toggle('visible', on);
    if (currentBubble) currentBubble.classList.toggle('streaming', on);
}

function useHint(text) {
    $('inputBox').value = text;
    $('inputBox').focus();
    autoResize($('inputBox'));
}

function smartCommit() {
    const push = false;
    vscode.postMessage({ type: 'smartCommit', push });
}

function cancelStream() {
    vscode.postMessage({ type: 'cancelStream' });
    setStreaming(false);
}

function clearChat() {
    $('messages').innerHTML = '';
    $('empty').classList.remove('hidden');
    codeBlocks = [];
    vscode.postMessage({ type: 'clearChat' });
}

function openBrowser() {
    vscode.postMessage({ type: 'openBrowser' });
}

function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

// ── Input ─────────────────────────────────────────────────────────────────────

$('inputBox').addEventListener('input',   () => autoResize($('inputBox')));
$('inputBox').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

// Update file label
window.addEventListener('message', msg => handleMessage(msg.data));
document.addEventListener('DOMContentLoaded', () => {
    vscode.postMessage({ type: 'getStatus' });
});
// Also on load
vscode.postMessage({ type: 'getStatus' });

function sendMessage() {
    const text = $('inputBox').value.trim();
    if (!text || streaming) return;
    const includeFile = $('fileCheck').checked;
    vscode.postMessage({ type: 'send', text, includeFile });
    $('inputBox').value = '';
    $('inputBox').style.height = 'auto';
}

// ── Render messages ───────────────────────────────────────────────────────────

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
    scrollBottom();
    setStreaming(true);
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

function finalizeMessage(blocks, hasEditor) {
    setStreaming(false);
    if (!currentBubble) return;

    // Re-render: replace plain text with formatted content
    const rawText = currentTextNode ? currentTextNode.textContent : '';
    currentBubble.innerHTML = '';

    // Split on code blocks
    const parts = rawText.split(/```(\w*)\n([\s\S]*?)```/g);
    let i = 0;
    while (i < parts.length) {
        if (i % 4 === 0) {
            // plain text segment
            if (parts[i]) {
                const t = document.createElement('span');
                t.style.whiteSpace = 'pre-wrap';
                t.textContent = parts[i];
                currentBubble.appendChild(t);
            }
        } else if (i % 4 === 1) {
            // language + code (next two captures)
            const lang = parts[i]   || 'code';
            const code = parts[i+1] || '';
            currentBubble.appendChild(makeCodeBlock(lang, code, hasEditor));
            i += 2;
        }
        i++;
    }

    // If no code blocks extracted from regex, try blocks array
    if (blocks && blocks.length && !rawText.includes('```')) {
        for (const b of blocks) {
            currentBubble.appendChild(makeCodeBlock(b.lang, b.code, hasEditor));
        }
    }

    currentBubble   = null;
    currentTextNode = null;
    scrollBottom();
}

function makeCodeBlock(lang, code, hasEditor) {
    const wrapper = document.createElement('div');
    wrapper.className = 'code-block';

    const header = document.createElement('div');
    header.className = 'code-header';

    const langSpan = document.createElement('span');
    langSpan.className = 'lang';
    langSpan.textContent = lang || 'code';
    header.appendChild(langSpan);

    const actions = document.createElement('div');
    actions.className = 'actions';

    // Copy button
    const copyBtn = document.createElement('button');
    copyBtn.textContent = '📋 Копировать';
    copyBtn.onclick = () => {
        navigator.clipboard.writeText(code).then(() => {
            copyBtn.textContent = '✓ Скопировано';
            setTimeout(() => { copyBtn.textContent = '📋 Копировать'; }, 1500);
        });
    };
    actions.appendChild(copyBtn);

    if (hasEditor) {
        // Insert at cursor
        const insBtn = document.createElement('button');
        insBtn.textContent = '⬇ Вставить';
        insBtn.onclick = () => vscode.postMessage({ type: 'applyCode', code, lang, action: 'insert' });
        actions.appendChild(insBtn);

        // Replace selection
        const repBtn = document.createElement('button');
        repBtn.textContent = '↺ Заменить выделение';
        repBtn.onclick = () => vscode.postMessage({ type: 'applyCode', code, lang, action: 'replace_selection' });
        actions.appendChild(repBtn);

        // Replace whole file
        const allBtn = document.createElement('button');
        allBtn.textContent = '⬆ Весь файл';
        allBtn.onclick = () => {
            if (confirm('Заменить весь файл этим кодом?')) {
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
    bub.textContent = '⚠ ' + text;
    div.appendChild(bub);
    $('messages').appendChild(div);
    currentBubble   = null;
    currentTextNode = null;
    scrollBottom();
}

// ── Status display ────────────────────────────────────────────────────────────

function updateStatus(online, ollama, groq, model, projects) {
    const dot  = $('statusDot');
    const text = $('statusText');
    if (online) {
        dot.className = 'dot on';
        const who = groq ? '☁️ Groq' : '🖥 Local';
        text.textContent = `${who} · ${model}`;
    } else {
        dot.className = 'dot off';
        text.textContent = 'AI Helper offline — запусти START.bat';
    }
}

// ── Message handler ───────────────────────────────────────────────────────────

function handleMessage(msg) {
    switch (msg.type) {
        case 'userMessage':    addUserMessage(msg.text, msg.file); break;
        case 'startResponse':  startAIMessage(); break;
        case 'appendText':     appendText(msg.text); break;
        case 'toolCall':       addToolCall(msg.label); break;
        case 'endResponse':    finalizeMessage(msg.codeBlocks, msg.hasEditor); break;
        case 'responseError':  showError(msg.error); break;
        case 'cancelledStream':setStreaming(false); break;
        case 'status':
            updateStatus(msg.online, msg.ollama, msg.groq, msg.model, msg.projects);
            break;
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

// ── Status bar item ───────────────────────────────────────────────────────────

let statusBar;

async function refreshStatusBar() {
    const r = await httpGet(apiBase() + '/status');
    if (statusBar) {
        if (r.ok) {
            const who = r.groq ? '☁️' : '🖥';
            statusBar.text      = `$(hubot) AI Helper ${who}`;
            statusBar.tooltip   = `AI Helper работает\nОллама: ${r.ollama ? '✓' : '✗'} | Groq: ${r.groq ? '✓' : '✗'}\nМодель: ${r.llm_model || '?'}\nКлик → открыть боковую панель`;
            statusBar.command   = 'aiHelper.chat.focus'; // focus the sidebar
        } else {
            statusBar.text    = `$(warning) AI Helper offline`;
            statusBar.tooltip = 'AI Helper недоступен. Запусти START.bat\nКлик → открыть панель';
            statusBar.command = 'aiHelper.chat.focus';
        }
    }
}

// ── Activation ────────────────────────────────────────────────────────────────

function activate(context) {
    // Sidebar provider
    const provider = new ChatViewProvider(context);
    context.subscriptions.push(
        vscode.window.registerWebviewViewProvider(ChatViewProvider.viewType, provider, {
            webviewOptions: { retainContextWhenHidden: true },
        })
    );

    // Status bar
    statusBar = vscode.window.createStatusBarItem(vscode.StatusBarAlignment.Right, 100);
    statusBar.text    = '$(hubot) AI Helper';
    statusBar.tooltip = 'AI Helper';
    statusBar.show();
    context.subscriptions.push(statusBar);

    // Refresh status periodically
    refreshStatusBar();
    const timer = setInterval(refreshStatusBar, 30_000);
    context.subscriptions.push({ dispose: () => clearInterval(timer) });

    // Update file label when active editor changes
    vscode.window.onDidChangeActiveTextEditor(editor => {
        if (provider._view && editor) {
            const name = path.basename(editor.document.fileName);
            provider._post({ type: 'activeFile', name });
        }
    }, null, context.subscriptions);

    // Commands
    context.subscriptions.push(
        vscode.commands.registerCommand('aiHelper.newChat', () => {
            vscode.commands.executeCommand('aiHelper.chat.focus');
        }),
        vscode.commands.registerCommand('aiHelper.smartCommit', async () => {
            const folder = vscode.workspace.workspaceFolders?.[0]?.uri?.fsPath || '';
            const r = await httpPost('/smart-commit', { push: false, project: folder });
            if (r.ok) vscode.window.showInformationMessage(`✓ Committed: ${r.message}`);
            else      vscode.window.showErrorMessage(`Smart commit: ${r.error || r.output || '?'}`);
            refreshStatusBar();
        }),
        vscode.commands.registerCommand('aiHelper.smartCommitPush', async () => {
            const folder = vscode.workspace.workspaceFolders?.[0]?.uri?.fsPath || '';
            const r = await httpPost('/smart-commit', { push: true, project: folder });
            if (r.ok) vscode.window.showInformationMessage(`✓ Committed${r.pushed ? ' + pushed' : ''}: ${r.message}`);
            else      vscode.window.showErrorMessage(`Smart commit+push: ${r.error || r.output || '?'}`);
            refreshStatusBar();
        }),
        vscode.commands.registerCommand('aiHelper.applyLastCode', () => {
            vscode.commands.executeCommand('aiHelper.chat.focus');
            vscode.window.showInformationMessage('Открой боковую панель AI Helper и нажми кнопку "Применить" под кодом');
        })
    );
}

function deactivate() {}

module.exports = { activate, deactivate };
