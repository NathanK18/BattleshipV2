const playerBoardEl = document.getElementById("playerBoard");
const cpuBoardEl = document.getElementById("cpuBoard");
const statusEl = document.getElementById("status");
const newGameBtn = document.getElementById("newGameBtn");
const themeSelect = document.getElementById("themeSelect");

let gameId = localStorage.getItem("gameId") || null;

let size = 10;
let state = "IDLE"; // PLACING | PLAYER_TURN | GAME_OVER | IDLE
let playerGrid = null;
let playerShots = null;
let cpuShots = null;

// placement state
let shipsToPlace = [];
let placedShips = [];
let placeDir = 0; // 0 horiz, 1 vert

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

  // Important: your CSS uses .board.clickable .cell
  playerBoardEl.classList.toggle("clickable", state === "PLACING");
  cpuBoardEl.classList.toggle("clickable", state === "PLAYER_TURN");

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

      if (state === "PLACING") {
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

      if (state === "PLAYER_TURN") {
        e.addEventListener("click", () => onEnemyCellClick(r, c));
      }

      cpuBoardEl.appendChild(e);
    }
  }
}

function updatePlacementStatus() {
  if (state !== "PLACING") return;
  const nextLen = shipsToPlace[placedShips.length];
  if (nextLen == null) return;

  const dirLabel = placeDir === 0 ? "HORIZONTAL" : "VERTICAL";
  setStatus(`Place ship length ${nextLen} (${placedShips.length + 1}/${shipsToPlace.length}). Press R to rotate (${dirLabel}).`);
}

function applyServerState(data) {
  if (data.gameId) {
    gameId = data.gameId;
    localStorage.setItem("gameId", gameId);
  }
  size = data.size ?? size;
  state = data.state ?? state;

  shipsToPlace = Array.isArray(data.shipsToPlace) ? data.shipsToPlace : shipsToPlace;
  playerGrid = data.playerGrid ?? playerGrid;
  playerShots = data.playerShots ?? playerShots;
  cpuShots = data.cpuShots ?? cpuShots;

  renderBoards();
  if (data.status) setStatus(data.status);
  if (state === "PLACING") updatePlacementStatus();
}

async function resumeGame() {
  if (!gameId) return false;

  try {
    const res = await fetch("api_state.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ gameId })
    });

    const text = await res.text();
    const data = JSON.parse(text);

    if (!data.ok) return false;

    // IMPORTANT: on resume, we don't know partial placements; start fresh placement client-side.
    if (data.state === "PLACING") {
      placedShips = [];
      placeDir = 0;
    }

    applyServerState(data);
    newGameBtn.textContent = "Restart Game";
    return true;
  } catch (err) {
    console.error("resumeGame failed", err);
    return false;
  }
}

async function startGame() {
  try {
    const res = await fetch("api_start.php", { method: "POST" });

    // Read as text first so we can debug non-JSON PHP errors
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch {
      setStatus("Server did not return JSON. Open api_start.php directly to see the error.");
      console.error("Non-JSON response:", text);
      return;
    }

    if (!data.ok) {
      setStatus(data.error || "Failed to start game");
      console.error("api_start ok:false", data);
      return;
    }

    placedShips = [];
    placeDir = 0;

    applyServerState(data);
    newGameBtn.textContent = "Restart Game";
  } catch (err) {
    setStatus("Network / server error. Check console + that PHP server is running.");
    console.error(err);
  }
}

async function onPlayerCellClick(r, c) {
  if (state !== "PLACING") return;

  const len = shipsToPlace[placedShips.length];
  if (len == null) return;

  const gridCopy = playerGrid.map(row => row.slice());

  if (!canPlaceLocal(gridCopy, r, c, len, placeDir)) {
    setStatus("Invalid placement (overlap/out of bounds/adjacent). Try another spot. Press R to rotate.");
    return;
  }

  placeShipLocal(gridCopy, r, c, len, placeDir);
  playerGrid = gridCopy;
  placedShips.push({ r, c, len, dir: placeDir });

  renderBoards();

  if (placedShips.length === shipsToPlace.length) {
    try {
      const res = await fetch("api_place.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ gameId, ships: placedShips })
      });

      const text = await res.text();
      const data = JSON.parse(text);

      if (!data.ok) {
        setStatus((data.error || "Server rejected placement") + " Restarting…");
        await startGame();
        return;
      }

      applyServerState(data);
      return;
    } catch (err) {
      setStatus("Network error while submitting placement.");
      console.error(err);
      return;
    }
  }

  updatePlacementStatus();
}

async function onEnemyCellClick(r, c) {
  if (state !== "PLAYER_TURN") return;
  if (!playerShots) return;

  if (playerShots[r][c] !== 0) {
    setStatus("You already shot there.");
    return;
  }

  try {
    const res = await fetch("api_shot.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ gameId, r, c })
    });

    const text = await res.text();
    const data = JSON.parse(text);

    if (!data.ok) {
      setStatus(data.error || "Shot failed");
      return;
    }

    applyServerState(data);
  } catch (err) {
    setStatus("Network error.");
    console.error(err);
  }
}

// Rotate with R during placement
window.addEventListener("keydown", (e) => {
  if (state !== "PLACING") return;
  if (e.key.toLowerCase() === "r") {
    placeDir = placeDir === 0 ? 1 : 0;
    updatePlacementStatus();
  }
});

// Theme picker (unchanged)
themeSelect.addEventListener("change", () => {
  const t = themeSelect.value;
  const url = new URL(window.location.href);
  url.searchParams.set("theme", t);
  window.location.href = url.toString();
});

newGameBtn.addEventListener("click", startGame);

// Init
(async function init() {
  renderBoards();
  setStatus("Press “Restart Game” to begin.");
  await resumeGame();
})();
