// «Приколюшки»: кликер, конфетти и убегающая кнопка. Чистый JS.

// 1) Кликер со счётом.
let score = 0;
document.getElementById("clickTarget")?.addEventListener("click", (e) => {
  score++;
  document.getElementById("score").textContent = score;
  e.target.style.transform = `scale(${1 + Math.random() * 0.2})`;
  setTimeout(() => (e.target.style.transform = "scale(1)"), 100);
});

// 2) Конфетти на canvas.
document.getElementById("confettiBtn")?.addEventListener("click", () => {
  const c = document.getElementById("confetti");
  const ctx = c.getContext("2d");
  const parts = Array.from({ length: 120 }, () => ({
    x: c.width / 2,
    y: c.height / 2,
    vx: (Math.random() - 0.5) * 8,
    vy: (Math.random() - 0.5) * 8 - 2,
    color: `hsl(${Math.random() * 360},90%,60%)`,
    life: 60,
  }));
  (function frame() {
    ctx.clearRect(0, 0, c.width, c.height);
    let alive = false;
    for (const p of parts) {
      if (p.life <= 0) continue;
      alive = true;
      p.x += p.vx;
      p.y += p.vy;
      p.vy += 0.15; // гравитация
      p.life--;
      ctx.fillStyle = p.color;
      ctx.fillRect(p.x, p.y, 4, 4);
    }
    if (alive) requestAnimationFrame(frame);
  })();
});

// 3) Убегающая кнопка.
const runner = document.getElementById("runner");
runner?.addEventListener("mouseover", () => {
  const area = runner.parentElement.getBoundingClientRect();
  const maxX = area.width - runner.offsetWidth;
  const maxY = area.height - runner.offsetHeight;
  runner.style.position = "relative";
  runner.style.left = Math.max(0, Math.random() * maxX) + "px";
  runner.style.top = Math.max(0, Math.random() * maxY - 20) + "px";
});
runner?.addEventListener("click", () => alert("Поймал! 🎉"));
