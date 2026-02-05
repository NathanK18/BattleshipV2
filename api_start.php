<?php
session_start();
header("Content-Type: application/json");

const SIZE = 10;

// ships: length => count  (exactly 3 ships total)
$ships = [
  5 => 1,
  3 => 1,
  2 => 1
];

function emptyGrid() {
  $g = [];
  for ($r=0; $r<SIZE; $r++) {
    $row = array_fill(0, SIZE, 0); // 0 empty, 1 ship
    $g[] = $row;
  }
  return $g;
}

function emptyShots() {
  $s = [];
  for ($r=0; $r<SIZE; $r++) {
    $row = array_fill(0, SIZE, 0); // 0 unknown, 1 miss, 2 hit
    $s[] = $row;
  }
  return $s;
}

function canPlace(&$grid, $r, $c, $len, $dir) {
  // dir: 0 horiz, 1 vert
  if ($dir === 0) {
    if ($c + $len > SIZE) return false;
    for ($i=0; $i<$len; $i++) if ($grid[$r][$c+$i] !== 0) return false;
  } else {
    if ($r + $len > SIZE) return false;
    for ($i=0; $i<$len; $i++) if ($grid[$r+$i][$c] !== 0) return false;
  }

  // "no-touch" rule (including diagonals)
  for ($rr=max(0,$r-1); $rr<=min(SIZE-1,$r+($dir? $len:1)); $rr++) {
    for ($cc=max(0,$c-1); $cc<=min(SIZE-1,$c+($dir?1:$len)); $cc++) {
      if ($grid[$rr][$cc] === 1) return false;
    }
  }

  return true;
}

function placeShip(&$grid, $r, $c, $len, $dir) {
  if ($dir === 0) {
    for ($i=0; $i<$len; $i++) $grid[$r][$c+$i] = 1;
  } else {
    for ($i=0; $i<$len; $i++) $grid[$r+$i][$c] = 1;
  }
}

function randomPlaceAllShips(&$grid, $ships) {
  foreach ($ships as $len => $count) {
    for ($n=0; $n<$count; $n++) {
      $tries = 0;
      while (true) {
        $tries++;
        if ($tries > 5000) throw new Exception("Could not place ships (too many tries)");
        $dir = random_int(0,1);
        $r = random_int(0, SIZE-1);
        $c = random_int(0, SIZE-1);
        if (canPlace($grid, $r, $c, $len, $dir)) {
          placeShip($grid, $r, $c, $len, $dir);
          break;
        }
      }
    }
  }
}

function countShipCells($grid) {
  $cells = 0;
  for ($r=0; $r<SIZE; $r++) for ($c=0; $c<SIZE; $c++) if ($grid[$r][$c] === 1) $cells++;
  return $cells;
}

try {
  $playerGrid = emptyGrid();
  $cpuGrid    = emptyGrid();

  randomPlaceAllShips($playerGrid, $ships);
  randomPlaceAllShips($cpuGrid, $ships);

  $_SESSION["game"] = [
    "playerGrid" => $playerGrid,
    "cpuGrid"    => $cpuGrid,

    "playerShots" => emptyShots(), // shots fired at CPU
    "cpuShots"    => emptyShots(), // shots fired at player

    "playerHits" => 0,
    "cpuHits"    => 0,
    "totalShipCells" => countShipCells($cpuGrid),
    "turn" => "player",

    // simple AI memory
    "aiTargets" => [],
  ];

  echo json_encode([
    "ok" => true,
    "size" => SIZE,
    "playerGrid" => $playerGrid, // safe to show own ships
    "playerShots" => $_SESSION["game"]["playerShots"],
    "cpuShots" => $_SESSION["game"]["cpuShots"],
    "status" => "New game started. Fire on the enemy board!"
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
