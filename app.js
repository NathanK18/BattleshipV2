const playerBoardEl = document.getElementById("playerBoard");
const cpuBoardEl = document.getElementById("cpuBoard");
const statusEl = document.getElementById("status");
const newGameBtn = document.getElementById("newGameBtn");
const themeSelect = document.getElementById("themeSelect");

let size = 10;
let phase = "idle"; // idle | placement | battle
let playerGrid = null;
let playerShots = null;
let cpuShots = null;

// placement state
let shipsToPlace = [];     // e.g. [5,3,2]
let placedShips = [];      // {r,c,len,dir}
// Placement direction removed: ships always place horizontally.
const PLACE_DIR = 0;

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

function inBounds(r, c) {
  return r >= 0 && r < size && c >= 0 && c < size;
}

// local validation mirrors server rules (server is source of truth)
function canPlaceLocal(grid, r, c, len, dir) {
  if (!inBounds(r, c)) return false;
  if (dir !== 0 && dir !== 1) return false;

  if (dir === 0) {
    if (c + len > size) return false;
    for (let i = 0; i < len; i++) if (grid[r][c + i] !== 0) return false;
  } else {
    if (r + len > size) return false;
    for (let i = 0; i < len; i++) if (grid[r + i][c] !== 0) return false;
  }

  // "no-touch" rule (incl diagonals)
  const r2 = dir ? (r + len - 1) : r;
  const c2 = dir ? c : (c + len - 1);

  for (let rr = Math.max(0, r - 1); rr <= Math.min(size - 1, r2 + 1); rr++) {
    for (let cc = Math.max(0, c - 1); cc <= Math.min(size - 1, c2 + 1); cc++) {
      if (grid[rr][cc] === 1) return false;
    }
  }

  return true;
}

function placeShipLocal(grid, r, c, len, dir) {
  if (dir === 0) {
    for (let i = 0; i < len; i++) grid[r][c + i] = 1;
  } else {
    for (let i = 0; i < len; i++) grid[r + i][c] = 1;
  }
}

function renderBoards() {
  playerBoardEl.innerHTML = "";
  cpuBoardEl.innerHTML = "";

  // Make it visually obvious which board is currently clickable.
  playerBoardEl.classList.toggle("clickable", phase === "placement");
  cpuBoardEl.classList.toggle("clickable", phase === "battle");

  playerBoardEl.style.gridTemplateColumns = `repeat(${size}, 1fr)`;
  cpuBoardEl.style.gridTemplateColumns = `repeat(${size}, 1fr)`;

  for (let r = 0; r < size; r++) {
    for (let c = 0; c < size; c++) {
      // Player board
      const p = cellDiv(r, c);

      if (playerGrid && playerGrid[r][c] === 1) p.classList.add("ownShip");
      if (cpuShots) {
        if (cpuShots[r][c] === 1) p.classList.add("miss");
        if (cpuShots[r][c] === 2) p.classList.add("hit");
      }

      if (phase === "placement") {
        p.addEventListener("click", () => onPlayerCellClick(r, c));
      }

      playerBoardEl.appendChild(p);

      // CPU board
      const e = cellDiv(r, c);

      if (playerShots) {
        if (playerShots[r][c] === 0) e.classList.add("unknown");
        if (playerShots[r][c] === 1) e.classList.add("miss");
        if (playerShots[r][c] === 2) e.classList.add("hit");
      } else {
        e.classList.add("unknown");
      }

      if (phase === "battle") {
        e.addEventListener("click", () => onEnemyCellClick(r, c));
      }

      cpuBoardEl.appendChild(e);
    }
  }
}

function updatePlacementStatus() {
  if (phase !== "placement") return;
  const nextLen = shipsToPlace[placedShips.length];
  if (nextLen == null) return;
  setStatus(`Place ship length ${nextLen} (${placedShips.length + 1}/${shipsToPlace.length}) by clicking your board.`);
}

async function startGame() {
  const res = await fetch("api_start.php", { method: "POST" });
  const data = await res.json();

  if (!data.ok) {
    setStatus(data.error || "Failed to start game");
    return;
  }

  size = data.size;
  phase = data.phase || "battle";
  playerGrid = data.playerGrid;
  playerShots = data.playerShots;
  cpuShots = data.cpuShots;

  shipsToPlace = Array.isArray(data.shipsToPlace) ? data.shipsToPlace : [];
  placedShips = [];

  renderBoards();
  setStatus(data.status);

  if (phase === "placement") updatePlacementStatus();

  newGameBtn.textContent = "Restart Game";
}

function isGameOverMessage(msg) {
  const m = msg.toLowerCase();
  return m.includes("you win") || m.includes("computer wins") || m.includes("game is over");
}

async function onPlayerCellClick(r, c) {
  if (phase !== "placement") return;

  const len = shipsToPlace[placedShips.length];
  if (len == null) return;

  const gridCopy = playerGrid.map(row => row.slice());
  if (!canPlaceLocal(gridCopy, r, c, len, PLACE_DIR)) {
    setStatus("Invalid placement (overlap/out of bounds/adjacent). Try another spot.");
    return;
  }

  placeShipLocal(gridCopy, r, c, len, PLACE_DIR);
  playerGrid = gridCopy;
  placedShips.push({ r, c, len, dir: PLACE_DIR });

  renderBoards();

  if (placedShips.length === shipsToPlace.length) {
    try {
      const res = await fetch("api_place.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ships: placedShips })
      });
      const data = await res.json();

      if (!data.ok) {
        setStatus((data.error || "Server rejected placement") + " Restarting…");
        await startGame();
        return;
      }

      phase = "battle";
      playerGrid = data.playerGrid;
      playerShots = data.playerShots;
      cpuShots = data.cpuShots;

      renderBoards();
      setStatus(data.status);
      return;
    } catch (e) {
      setStatus("Network error while submitting placement.");
      return;
    }
  }

  updatePlacementStatus();
}

async function onEnemyCellClick(r, c) {
  if (!playerShots) {
    setStatus("Press “Restart Game” to begin.");
    return;
  }

  if (phase !== "battle") {
    setStatus("Place your fleet first.");
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

// Theme picker
themeSelect.addEventListener("change", () => {
  const t = themeSelect.value;
  const url = new URL(window.location.href);
  url.searchParams.set("theme", t);
  window.location.href = url.toString();
});

newGameBtn.addEventListener("click", startGame);

// initial paint
renderBoards();
