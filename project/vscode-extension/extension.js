// AI Helper VS Code Extension
// Calls AI Helper REST API (localhost:8502) and shows responses in panels.
// Install: see README.md in this folder.

const vscode = require('vscode');
const http   = require('http');
const https  = require('https');
const path   = require('path');

// ── Config ───────────────────────────────────────────────────────────────────

function cfg() {
    return vscode.workspace.getConfiguration('aiHelper');
}
function apiUrl()  { return cfg().get('apiUrl',  'http://localhost:8502'); }
function chatUrl() { return cfg().get('chatUrl', 'http://localhost:8501'); }

// ── HTTP helper ───────────────────────────────────────────────────────────────

function apiPost(endpoint, body) {
    return new Promise((resolve, reject) => {
        const base   = new URL(apiUrl());
        const lib    = base.protocol === 'https:' ? https : http;
        const data   = JSON.stringify(body);
        const opts   = {
            hostname: base.hostname,
            port:     parseInt(base.port) || (base.protocol === 'https:' ? 443 : 80),
            path:     endpoint,
            method:   'POST',
            headers:  { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(data) },
        };
        const req = lib.request(opts, res => {
            let out = '';
            res.on('data', c => out += c);
            res.on('end',  () => {
                try   { resolve(JSON.parse(out)); }
                catch { resolve({ ok: false, response: out }); }
            });
        });
        req.on('error', reject);
        req.setTimeout(300_000, () => { req.destroy(new Error('Timeout 5m')); });
        req.write(data);
        req.end();
    });
}

function apiGet(endpoint) {
    return new Promise((resolve, reject) => {
        const base = new URL(apiUrl());
        const lib  = base.protocol === 'https:' ? https : http;
        const opts = {
            hostname: base.hostname,
            port:     parseInt(base.port) || 80,
            path:     endpoint,
            method:   'GET',
        };
        const req = lib.request(opts, res => {
            let out = '';
            res.on('data', c => out += c);
            res.on('end',  () => {
                try   { resolve(JSON.parse(out)); }
                catch { resolve({ ok: false }); }
            });
        });
        req.on('error', reject);
        req.setTimeout(10_000, () => req.destroy());
        req.end();
    });
}

// ── Status bar ────────────────────────────────────────────────────────────────

let statusBar;

function updateStatusBar(ok, detail = '') {
    if (!statusBar) return;
    if (ok) {
        statusBar.text        = '$(robot) AI Helper';
        statusBar.tooltip     = `AI Helper работает\n${detail}\nClick → открыть чат`;
        statusBar.color       = new vscode.ThemeColor('statusBarItem.prominentForeground');
        statusBar.command     = 'aiHelper.openChat';
    } else {
        statusBar.text        = '$(warning) AI Helper offline';
        statusBar.tooltip     = 'AI Helper недоступен. Запусти START.bat';
        statusBar.color       = new vscode.ThemeColor('errorForeground');
        statusBar.command     = 'aiHelper.status';
    }
}

async function refreshStatus() {
    try {
        const r = await apiGet('/status');
        if (r.ok) {
            const detail = `Ollama: ${r.ollama ? '✓' : '✗'} | Groq: ${r.groq ? '✓' : '✗'} | Проекты: ${(r.projects || []).join(', ') || 'нет'}`;
            updateStatusBar(true, detail);
            return true;
        }
    } catch (_) {}
    updateStatusBar(false);
    return false;
}

// ── WebView panel ─────────────────────────────────────────────────────────────

function makePanel(title, content, isMarkdown = false) {
    const panel = vscode.window.createWebviewPanel(
        'aiHelperResponse',
        title,
        vscode.ViewColumn.Beside,
        { enableScripts: false, retainContextWhenHidden: true }
    );

    const escaped = content.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

    // Simple markdown: code blocks and bold
    const html = isMarkdown
        ? escaped
            .replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code class="lang-$1">$2</code></pre>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>')
        : `<pre style="white-space:pre-wrap">${escaped}</pre>`;

    panel.webview.html = `<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: var(--vscode-font-family); font-size:13px; padding:16px; background:var(--vscode-editor-background); color:var(--vscode-editor-foreground); }
  pre  { background:var(--vscode-textCodeBlock-background); padding:10px; border-radius:4px; overflow:auto; }
  code { font-family:var(--vscode-editor-font-family); font-size:12px; }
  h1,h2,h3 { color:var(--vscode-textLink-foreground); }
  strong { color:var(--vscode-terminal-ansiBrightYellow); }
</style>
</head>
<body>${html}</body>
</html>`;

    return panel;
}

// ── Build message from editor context ────────────────────────────────────────

function getEditorContext() {
    const editor = vscode.window.activeTextEditor;
    if (!editor) return { file: '', code: '', lang: '' };

    const doc       = editor.document;
    const selection = editor.selection;
    const code      = doc.getText(selection.isEmpty ? undefined : selection);
    const lang      = doc.languageId;
    const file      = doc.fileName;
    return { file, code, lang };
}

function buildMessage(question, code, lang, fileName) {
    if (!code) return question;
    const fn  = fileName ? path.basename(fileName) : '';
    const hdr = fn ? `[Файл: ${fn}]` : '';
    return `${hdr}\n\`\`\`${lang}\n${code.slice(0, 8000)}\n\`\`\`\n\n${question}`;
}

// ── Commands ──────────────────────────────────────────────────────────────────

async function cmdAsk() {
    const question = await vscode.window.showInputBox({
        prompt:      'Спроси AI Helper',
        placeHolder: 'Что делает этот код? Как исправить ошибку?',
    });
    if (!question) return;

    const { file, code, lang } = getEditorContext();
    const message = buildMessage(question, code, lang, file);

    await vscode.window.withProgress(
        { location: vscode.ProgressLocation.Notification, title: 'AI Helper думает...', cancellable: false },
        async () => {
            try {
                const r = await apiPost('/chat', { message });
                if (r.ok) {
                    makePanel(`AI: ${question.slice(0, 40)}`, r.response, true);
                } else {
                    vscode.window.showErrorMessage(`AI Helper: ${r.error || 'Ошибка'}`);
                }
            } catch (e) {
                vscode.window.showErrorMessage(`AI Helper недоступен: ${e.message}. Запусти START.bat`);
            }
        }
    );
}

async function cmdFixBugs() {
    const { file, code, lang } = getEditorContext();
    if (!code) {
        vscode.window.showWarningMessage('Выдели код или открой файл');
        return;
    }
    const message = buildMessage(
        'Найди все баги, проблемы и уязвимости. Предложи исправленный код.',
        code, lang, file
    );
    await vscode.window.withProgress(
        { location: vscode.ProgressLocation.Notification, title: 'AI Helper анализирует...', cancellable: false },
        async () => {
            try {
                const r = await apiPost('/chat', { message });
                makePanel('Анализ багов', r.response || r.error || 'Нет ответа', true);
            } catch (e) {
                vscode.window.showErrorMessage(`Ошибка: ${e.message}`);
            }
        }
    );
}

async function cmdExplain() {
    const { file, code, lang } = getEditorContext();
    if (!code) {
        vscode.window.showWarningMessage('Выдели код для объяснения');
        return;
    }
    const message = buildMessage('Объясни этот код подробно, простым языком.', code, lang, file);
    await vscode.window.withProgress(
        { location: vscode.ProgressLocation.Notification, title: 'AI Helper объясняет...', cancellable: false },
        async () => {
            try {
                const r = await apiPost('/chat', { message });
                makePanel('Объяснение кода', r.response || r.error, true);
            } catch (e) {
                vscode.window.showErrorMessage(`Ошибка: ${e.message}`);
            }
        }
    );
}

async function cmdWriteTests() {
    const { file, code, lang } = getEditorContext();
    if (!code) {
        vscode.window.showWarningMessage('Выдели код для которого писать тесты');
        return;
    }
    const framework = lang === 'python' ? 'pytest' : lang === 'javascript' || lang === 'typescript' ? 'jest' : 'unit tests';
    const message   = buildMessage(`Напиши тесты ${framework} для этого кода. Покрой основные случаи и граничные.`, code, lang, file);
    await vscode.window.withProgress(
        { location: vscode.ProgressLocation.Notification, title: 'AI Helper пишет тесты...', cancellable: false },
        async () => {
            try {
                const r = await apiPost('/chat', { message });
                makePanel('Тесты', r.response || r.error, true);
            } catch (e) {
                vscode.window.showErrorMessage(`Ошибка: ${e.message}`);
            }
        }
    );
}

async function cmdSmartCommit(push = false) {
    const folder = vscode.workspace.workspaceFolders?.[0]?.uri?.fsPath || '';
    await vscode.window.withProgress(
        { location: vscode.ProgressLocation.Notification, title: 'AI генерирует commit message...', cancellable: false },
        async () => {
            try {
                const r = await apiPost('/smart-commit', { push, project: folder });
                if (r.ok) {
                    const detail = push && r.pushed ? ' + pushed' : '';
                    vscode.window.showInformationMessage(`✓ Committed${detail}: ${r.message}`);
                    refreshStatus();
                } else {
                    vscode.window.showErrorMessage(`Smart commit: ${r.error || r.output || 'Ошибка'}`);
                }
            } catch (e) {
                vscode.window.showErrorMessage(`AI Helper недоступен: ${e.message}`);
            }
        }
    );
}

async function cmdOpenChat() {
    vscode.env.openExternal(vscode.Uri.parse(chatUrl()));
}

async function cmdStatus() {
    const ok = await refreshStatus();
    if (ok) {
        const r = await apiGet('/status');
        const msg = [
            `Ollama: ${r.ollama ? '✓ работает' : '✗ недоступен'}`,
            `Groq: ${r.groq ? '✓ ключ есть' : '✗ ключ не задан'}`,
            `Проекты: ${(r.projects||[]).join(', ') || 'нет'}`,
            `Модель: ${r.llm_model || '?'}`,
        ].join('\n');
        vscode.window.showInformationMessage(`AI Helper работает\n\n${msg}`, { modal: false });
    } else {
        const choice = await vscode.window.showWarningMessage(
            'AI Helper недоступен. Запусти START.bat в папке проекта.',
            'Открыть папку'
        );
        if (choice === 'Открыть папку') {
            vscode.commands.executeCommand('revealFileInOS', vscode.Uri.file(
                vscode.workspace.workspaceFolders?.[0]?.uri?.fsPath || ''
            ));
        }
    }
}

// ── Activation ────────────────────────────────────────────────────────────────

function activate(context) {
    // Status bar
    statusBar = vscode.window.createStatusBarItem(vscode.StatusBarAlignment.Right, 100);
    statusBar.show();
    context.subscriptions.push(statusBar);

    // Register commands
    const commands = [
        ['aiHelper.ask',              cmdAsk],
        ['aiHelper.fixBugs',          cmdFixBugs],
        ['aiHelper.explain',          cmdExplain],
        ['aiHelper.writeTests',       cmdWriteTests],
        ['aiHelper.smartCommit',      () => cmdSmartCommit(false)],
        ['aiHelper.smartCommitPush',  () => cmdSmartCommit(true)],
        ['aiHelper.openChat',         cmdOpenChat],
        ['aiHelper.status',           cmdStatus],
    ];
    for (const [id, fn] of commands) {
        context.subscriptions.push(vscode.commands.registerCommand(id, fn));
    }

    // Initial status check + periodic refresh every 30s
    refreshStatus();
    const timer = setInterval(refreshStatus, 30_000);
    context.subscriptions.push({ dispose: () => clearInterval(timer) });
}

function deactivate() {}

module.exports = { activate, deactivate };
