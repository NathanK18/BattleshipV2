const playerBoardEl = document.getElementById("playerBoard");
const cpuBoardEl = document.getElementById("cpuBoard");
const statusEl = document.getElementById("status");
const newGameBtn = document.getElementById("newGameBtn");
const themeSelect = document.getElementById("themeSelect");

let size = 10;
let playerGrid = null;
let playerShots = null; // shots at CPU
let cpuShots = null;    // shots at player

function setStatus(msg) {
  statusEl.textContent = msg;
}

function cellDiv(r, c) {
  const d = document.createElement("div");
  d.className = "cell";
  d.dataset.r = String(r);
  d.dataset.c = String(c);
  return d;
}

function renderBoards() {
  playerBoardEl.innerHTML = "";
  cpuBoardEl.innerHTML = "";

  playerBoardEl.style.gridTemplateColumns = `repeat(${size}, 1fr)`;
  cpuBoardEl.style.gridTemplateColumns = `repeat(${size}, 1fr)`;

  for (let r=0; r<size; r++) {
    for (let c=0; c<size; c++) {
      // Player board (show ships)
      const p = cellDiv(r,c);

      if (playerGrid && playerGrid[r][c] === 1) p.classList.add("ownShip");
      if (cpuShots) {
        if (cpuShots[r][c] === 1) p.classList.add("miss");
        if (cpuShots[r][c] === 2) p.classList.add("hit");
      }

      playerBoardEl.appendChild(p);

      // CPU board (hide ships; show only shot results)
      const e = cellDiv(r,c);

      if (playerShots) {
        if (playerShots[r][c] === 0) e.classList.add("unknown");
        if (playerShots[r][c] === 1) e.classList.add("miss");
        if (playerShots[r][c] === 2) e.classList.add("hit");
      } else {
        e.classList.add("unknown");
      }

      e.addEventListener("click", () => onEnemyCellClick(r,c));
      cpuBoardEl.appendChild(e);
    }
  }
}

async function startGame() {
  const res = await fetch("api_start.php", { method: "POST" });
  const data = await res.json();

  if (!data.ok) {
    setStatus(data.error || "Failed to start game");
    return;
  }

  size = data.size;
  playerGrid = data.playerGrid;
  playerShots = data.playerShots;
  cpuShots = data.cpuShots;

  renderBoards();
  setStatus(data.status);
  newGameBtn.textContent = "Restart Game";
}

function isGameOverMessage(msg) {
  const m = msg.toLowerCase();
  return m.includes("you win") || m.includes("computer wins") || m.includes("game is over");
}

async function onEnemyCellClick(r, c) {
  if (!playerShots) {
    setStatus("Press “Restart Game” to begin.");
    return;
  }

  if (isGameOverMessage(statusEl.textContent)) {
    setStatus("Game finished. Press “Restart Game” to play again.");
    return;
  }

  if (playerShots[r][c] !== 0) {
    setStatus("You already shot there.");
    return;
  }

  try {
    const res = await fetch("api_shot.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ r, c })
    });
    const data = await res.json();

    if (!data.ok) {
      setStatus(data.error || "Shot failed");
      return;
    }

    playerShots = data.playerShots;
    cpuShots = data.cpuShots;
    playerGrid = data.playerGrid;

    renderBoards();
    setStatus(data.status);

    if (isGameOverMessage(data.status)) {
      newGameBtn.textContent = "Restart Game";
      newGameBtn.focus();
    }
  } catch (e) {
    setStatus("Network error (is Apache running?)");
  }
}

// Theme picker: reload with ?theme=...
themeSelect.addEventListener("change", () => {
  const t = themeSelect.value;
  const url = new URL(window.location.href);
  url.searchParams.set("theme", t);
  window.location.href = url.toString();
});

newGameBtn.addEventListener("click", startGame);

// initial paint
renderBoards();
