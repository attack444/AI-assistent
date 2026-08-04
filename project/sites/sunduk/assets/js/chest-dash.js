/** Chest Dash — мобильная аркада в canvas */
(function () {
  function boot(canvas) {
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    const scoreEl = document.getElementById("score");
    const bestEl = document.getElementById("best");
    const restartBtn = document.getElementById("restart");
    const W = canvas.width;
    const H = canvas.height;
    const BEST_KEY = "sunduk-chest-dash-best";

    let best = Number(localStorage.getItem(BEST_KEY) || 0);
    if (bestEl) bestEl.textContent = String(best);

    let pointerX = W / 2;
    let running = true;
    let score = 0;
    let time = 0;
    let items = [];
    let shake = 0;
    const chest = { x: W / 2, y: H - 58, w: 74, h: 40 };

    function spawn() {
      const good = Math.random() > 0.28;
      items.push({
        x: 40 + Math.random() * (W - 80),
        y: -24,
        r: good ? 10 + Math.random() * 6 : 12,
        vy: 2.2 + Math.random() * 2.4 + Math.min(3, score / 40),
        good: good,
      });
    }

    function reset() {
      score = 0;
      time = 0;
      items = [];
      running = true;
      if (scoreEl) scoreEl.textContent = "0";
      spawn();
    }

    function end() {
      running = false;
      if (score > best) {
        best = score;
        localStorage.setItem(BEST_KEY, String(best));
        if (bestEl) bestEl.textContent = String(best);
      }
      shake = 10;
    }

    function drawBg() {
      const g = ctx.createLinearGradient(0, 0, 0, H);
      g.addColorStop(0, "#0a1520");
      g.addColorStop(1, "#04080d");
      ctx.fillStyle = g;
      ctx.fillRect(0, 0, W, H);
      ctx.strokeStyle = "rgba(215,226,236,0.06)";
      for (let x = 0; x < W; x += 40) {
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, H);
        ctx.stroke();
      }
    }

    function drawChest() {
      const x = chest.x - chest.w / 2;
      const y = chest.y - chest.h / 2;
      ctx.fillStyle = "#c6f135";
      ctx.fillRect(x, y + 10, chest.w, chest.h - 10);
      ctx.fillStyle = "#9fca18";
      ctx.fillRect(x, y, chest.w, 14);
      ctx.fillStyle = "#071018";
      ctx.fillRect(x + chest.w / 2 - 6, y + 16, 12, 10);
    }

    let prev = 0;
    function frame(ts) {
      if (!prev) prev = ts;
      const dt = Math.min(32, ts - prev);
      prev = ts;
      time += dt;
      const ox = shake ? (Math.random() - 0.5) * shake : 0;
      const oy = shake ? (Math.random() - 0.5) * shake : 0;
      if (shake > 0) shake *= 0.9;

      ctx.save();
      ctx.translate(ox, oy);
      drawBg();
      chest.x += (pointerX - chest.x) * 0.22;

      if (running && time > 480) {
        time = 0;
        spawn();
        if (score > 12 && Math.random() > 0.55) spawn();
      }

      for (let i = items.length - 1; i >= 0; i--) {
        const it = items[i];
        if (running) it.y += it.vy * (dt / 16);
        ctx.beginPath();
        ctx.arc(it.x, it.y, it.r, 0, Math.PI * 2);
        if (it.good) {
          ctx.fillStyle = "#ffd84a";
          ctx.fill();
          ctx.strokeStyle = "#ff5a36";
          ctx.lineWidth = 2;
          ctx.stroke();
        } else {
          ctx.fillStyle = "#ff5a36";
          ctx.fill();
        }
        if (running) {
          const nearX = Math.abs(it.x - chest.x) < chest.w * 0.48;
          const nearY = it.y > chest.y - chest.h * 0.55 && it.y < chest.y + chest.h * 0.35;
          if (nearX && nearY) {
            items.splice(i, 1);
            if (it.good) {
              score += 1;
              if (scoreEl) scoreEl.textContent = String(score);
            } else end();
            continue;
          }
          if (it.y > H + 30) items.splice(i, 1);
        }
      }

      drawChest();
      if (!running) {
        ctx.fillStyle = "rgba(7,16,24,0.55)";
        ctx.fillRect(0, 0, W, H);
        ctx.fillStyle = "#eef3f7";
        ctx.font = "800 32px Syne, sans-serif";
        ctx.textAlign = "center";
        ctx.fillText("Конец партии", W / 2, H / 2 - 8);
        ctx.fillStyle = "#c6f135";
        ctx.font = "700 16px Figtree, sans-serif";
        ctx.fillText("Тап / пробел / «Заново»", W / 2, H / 2 + 26);
      }
      ctx.restore();
      requestAnimationFrame(frame);
    }

    function setPointer(clientX) {
      const rect = canvas.getBoundingClientRect();
      pointerX = Math.max(40, Math.min(W - 40, ((clientX - rect.left) * W) / rect.width));
    }

    canvas.addEventListener("pointermove", function (e) {
      setPointer(e.clientX);
    });
    canvas.addEventListener("pointerdown", function (e) {
      canvas.setPointerCapture(e.pointerId);
      setPointer(e.clientX);
      if (!running) reset();
    });
    window.addEventListener("keydown", function (e) {
      if (e.code === "Space") {
        e.preventDefault();
        reset();
      }
    });
    if (restartBtn) restartBtn.addEventListener("click", reset);

    reset();
    requestAnimationFrame(frame);
  }

  document.addEventListener("DOMContentLoaded", function () {
    boot(document.getElementById("game"));
  });
})();
