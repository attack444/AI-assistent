// =============================================================================
//  DIFF-РЕЖИМ для агента.
//  Чтобы не гонять в LLM весь проект (это дорого и медленно), берём только то,
//  что реально изменилось: `git diff`. Разбираем его в JSON со списком файлов
//  и их изменениями и отправляем в модель.
//
//  Экономия: типовой diff — сотни токенов вместо десятков тысяч токенов всего
//  репозитория. Прямое снижение стоимости задачи в десятки раз.
// =============================================================================
const { execFile } = require("child_process");

// Запуск git в конкретном репозитории (без shell → безопасно от инъекций).
function git(repoDir, args) {
  return new Promise((resolve, reject) => {
    execFile("git", ["-C", repoDir, ...args], { maxBuffer: 10 * 1024 * 1024 }, (err, stdout) => {
      if (err) return reject(err);
      resolve(stdout);
    });
  });
}

// Собрать структуру изменений: [{ file, status, patch }]
// base — с чем сравнивать (по умолчанию рабочее дерево против HEAD).
async function collectDiff(repoDir, base = "HEAD") {
  // 1) список изменённых файлов со статусом (M/A/D/R)
  const nameStatus = await git(repoDir, ["diff", "--name-status", base]);
  const files = nameStatus
    .trim()
    .split("\n")
    .filter(Boolean)
    .map((line) => {
      const [status, ...rest] = line.split("\t");
      return { status, file: rest.join("\t") };
    });

  // 2) для каждого файла — сам патч (unified diff)
  const withPatches = [];
  for (const f of files) {
    if (f.status.startsWith("D")) {
      withPatches.push({ ...f, patch: "(файл удалён)" });
      continue;
    }
    const patch = await git(repoDir, ["diff", base, "--", f.file]);
    withPatches.push({ ...f, patch });
  }

  return { base, files: withPatches, fileCount: withPatches.length };
}

// Превратить diff в компактный промпт для модели.
function diffToPrompt(diff, instruction) {
  const parts = diff.files
    .map((f) => `### Файл: ${f.file} (${f.status})\n\`\`\`diff\n${f.patch}\n\`\`\``)
    .join("\n\n");
  return (
    `Ты — senior-инженер. Задача: ${instruction}\n\n` +
    `Ниже только ИЗМЕНЁННЫЕ файлы (git diff). Предложи правки в виде нового diff.\n\n` +
    parts
  );
}

module.exports = { collectDiff, diffToPrompt, git };
