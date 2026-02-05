<?php
session_start();
header("Content-Type: application/json");

const SIZE = 10;

// must match api_start.php
$shipsRequired = [
  5 => 1,
  3 => 1,
  2 => 1
];

function emptyGrid() {
  $g = [];
  for ($r=0; $r<SIZE; $r++) $g[] = array_fill(0, SIZE, 0);
  return $g;
}

function inBounds($r,$c){ return $r>=0 && $r<SIZE && $c>=0 && $c<SIZE; }

function canPlace(&$grid, $r, $c, $len, $dir) {
  if (!inBounds($r,$c)) return false;
  if ($len <= 0) return false;
  if ($dir !== 0 && $dir !== 1) return false;

  if ($dir === 0) {
    if ($c + $len > SIZE) return false;
    for ($i=0; $i<$len; $i++) if ($grid[$r][$c+$i] !== 0) return false;
  } else {
    if ($r + $len > SIZE) return false;
    for ($i=0; $i<$len; $i++) if ($grid[$r+$i][$c] !== 0) return false;
  }

  // no-touch rule (incl diagonals)
  $r2 = $dir ? ($r + $len - 1) : $r;
  $c2 = $dir ? $c : ($c + $len - 1);

  for ($rr=max(0,$r-1); $rr<=min(SIZE-1,$r2+1); $rr++) {
    for ($cc=max(0,$c-1); $cc<=min(SIZE-1,$c2+1); $cc++) {
      if ($grid[$rr][$cc] === 1) return false;
    }
  }
  return true;
}

function placeShip(&$grid, $r, $c, $len, $dir) {
  if ($dir === 0) for ($i=0; $i<$len; $i++) $grid[$r][$c+$i] = 1;
  else           for ($i=0; $i<$len; $i++) $grid[$r+$i][$c] = 1;
}

if (!isset($_SESSION["game"])) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"No game in session. Press Restart Game."]);
  exit;
}

$game = $_SESSION["game"];
if (($game["phase"] ?? "battle") !== "placement") {
  http_response_code(409);
  echo json_encode(["ok"=>false, "error"=>"Not in placement phase. Press Restart Game."]);
  exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$placements = $input["ships"] ?? null;
if (!is_array($placements)) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Missing ships array"]);
  exit;
}

// Validate ship lengths + counts
$need = $shipsRequired;
foreach ($placements as $p) {
  $len = $p["len"] ?? null;
  if (!is_int($len) || !isset($need[$len]) || $need[$len] <= 0) {
    http_response_code(400);
    echo json_encode(["ok"=>false, "error"=>"Invalid ship set"]);
    exit;
  }
  $need[$len]--;
}
foreach ($need as $len => $cnt) {
  if ($cnt !== 0) {
    http_response_code(400);
    echo json_encode(["ok"=>false, "error"=>"Invalid ship set"]);
    exit;
  }
}

// Validate placement geometry
$grid = emptyGrid();
foreach ($placements as $p) {
  $r = $p["r"] ?? null;
  $c = $p["c"] ?? null;
  $len = $p["len"] ?? null;
  $dir = $p["dir"] ?? null;

  if (!is_int($r) || !is_int($c) || !is_int($len) || !is_int($dir)) {
    http_response_code(400);
    echo json_encode(["ok"=>false, "error"=>"Bad ship payload"]);
    exit;
  }

  if (!canPlace($grid, $r, $c, $len, $dir)) {
    http_response_code(409);
    echo json_encode(["ok"=>false, "error"=>"Invalid placement (overlap/out of bounds/adjacent)"]);
    exit;
  }

  placeShip($grid, $r, $c, $len, $dir);
}

// Accept placement
$game["playerGrid"] = $grid;
$game["phase"] = "battle";
$game["turn"] = "player";

$_SESSION["game"] = $game;

echo json_encode([
  "ok" => true,
  "playerGrid" => $game["playerGrid"],
  "playerShots" => $game["playerShots"],
  "cpuShots" => $game["cpuShots"],
  "status" => "Fleet placed! Fire on the enemy board."
]);
