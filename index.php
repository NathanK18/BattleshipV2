<?php
session_start();

$themes = [
  "classic" => "Classic Navy",
  "neon"    => "Neon Grid",
  "pirate"  => "Pirate Map",
  "space"   => "Deep Space"
];

if (isset($_GET["theme"]) && isset($themes[$_GET["theme"]])) {
  $_SESSION["theme"] = $_GET["theme"];
}

$theme = $_SESSION["theme"] ?? "classic";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Battleship (Local)</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body data-theme="<?= htmlspecialchars($theme) ?>">
  <header class="topbar">
    <div>
      <h1>Battleship</h1>
      <p class="sub">User vs Computer </p>
    </div>

    <div class="controls">
      <label>
        Theme
        <select id="themeSelect">
          <?php foreach ($themes as $k => $label): ?>
            <option value="<?= htmlspecialchars($k) ?>" <?= $k === $theme ? "selected" : "" ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <button id="newGameBtn">Restart Game</button>
    </div>
  </header>

  <main class="wrap">
    <section class="panel">
      <h2>Your Fleet (Computer shoots here)</h2>
      <div class="legend">
        <span class="chip ownShip"></span> ship
        <span class="chip hit"></span> hit
        <span class="chip miss"></span> miss
      </div>
      <div id="playerBoard" class="board" aria-label="Player board"></div>
    </section>

    <section class="panel">
      <h2>Enemy Waters (Click to fire)</h2>
      <div class="legend">
        <span class="chip hit"></span> hit
        <span class="chip miss"></span> miss
        <span class="chip unknown"></span> unknown
      </div>
      <div id="cpuBoard" class="board clickable" aria-label="CPU board"></div>
      <div id="status" class="status">Press “Restart Game” to begin.</div>
    </section>
  </main>

  <footer class="foot">
  
  </footer>

  <script src="app.js"></script>
</body>
</html>
