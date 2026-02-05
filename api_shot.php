<?php
session_start();
header("Content-Type: application/json");

const SIZE = 10;

function inBounds($r,$c){ return $r>=0 && $r<SIZE && $c>=0 && $c<SIZE; }

function addTargets(&$game, $r, $c) {
  $cands = [
    [$r-1,$c], [$r+1,$c], [$r,$c-1], [$r,$c+1]
  ];
  foreach ($cands as $p) {
    if (!inBounds($p[0],$p[1])) continue;
    $game["aiTargets"][] = $p;
  }
}

function aiPickShot(&$game) {
  while (!empty($game["aiTargets"])) {
    $p = array_shift($game["aiTargets"]);
    [$r,$c] = $p;
    if ($game["cpuShots"][$r][$c] === 0) return [$r,$c];
  }

  do {
    $r = random_int(0, SIZE-1);
    $c = random_int(0, SIZE-1);
  } while ($game["cpuShots"][$r][$c] !== 0);

  return [$r,$c];
}

if (!isset($_SESSION["game"])) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"No game in session. Press Restart Game."]);
  exit;
}

$game = $_SESSION["game"];

// prevent shooting during placement
if (($game["phase"] ?? "battle") !== "battle") {
  http_response_code(409);
  echo json_encode(["ok"=>false, "error"=>"Place your fleet first."]);
  exit;
}

// NEW: prevent any moves after game ended
if (($game["turn"] ?? "") === "over") {
  http_response_code(409);
  echo json_encode(["ok"=>false, "error"=>"Game is over. Press Restart Game."]);
  exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$r = $input["r"] ?? null;
$c = $input["c"] ?? null;

if (!is_int($r) || !is_int($c) || !inBounds($r,$c)) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Invalid shot coordinates"]);
  exit;
}

if ($game["turn"] !== "player") {
  http_response_code(409);
  echo json_encode(["ok"=>false, "error"=>"Not your turn"]);
  exit;
}

// Player shoots CPU
if ($game["playerShots"][$r][$c] !== 0) {
  http_response_code(409);
  echo json_encode(["ok"=>false, "error"=>"You already shot there"]);
  exit;
}

$playerEvent = "miss";
if ($game["cpuGrid"][$r][$c] === 1) {
  $game["playerShots"][$r][$c] = 2;
  $game["playerHits"]++;
  $playerEvent = "hit";
} else {
  $game["playerShots"][$r][$c] = 1;
}

$total = $game["totalShipCells"];
if ($game["playerHits"] >= $total) {
  $game["turn"] = "over";
  $_SESSION["game"] = $game;
  echo json_encode([
    "ok"=>true,
    "playerEvent"=>$playerEvent,
    "cpuEvent"=>null,
    "cpuShot"=>null,
    "playerShots"=>$game["playerShots"],
    "cpuShots"=>$game["cpuShots"],
    "playerGrid"=>$game["playerGrid"],
    "status"=>"You win! 🎉 Press Restart Game to play again."
  ]);
  exit;
}

// CPU shoots Player
$game["turn"] = "cpu";
[$cr,$cc] = aiPickShot($game);

$cpuEvent = "miss";
if ($game["playerGrid"][$cr][$cc] === 1) {
  $game["cpuShots"][$cr][$cc] = 2;
  $game["cpuHits"]++;
  $cpuEvent = "hit";
  addTargets($game, $cr, $cc);
} else {
  $game["cpuShots"][$cr][$cc] = 1;
}

if ($game["cpuHits"] >= $total) {
  $game["turn"] = "over";
  $_SESSION["game"] = $game;
  echo json_encode([
    "ok"=>true,
    "playerEvent"=>$playerEvent,
    "cpuEvent"=>$cpuEvent,
    "cpuShot"=>["r"=>$cr,"c"=>$cc],
    "playerShots"=>$game["playerShots"],
    "cpuShots"=>$game["cpuShots"],
    "playerGrid"=>$game["playerGrid"],
    "status"=>"Computer wins! 💥 Press Restart Game to play again."
  ]);
  exit;
}

$game["turn"] = "player";
$_SESSION["game"] = $game;

echo json_encode([
  "ok"=>true,
  "playerEvent"=>$playerEvent,
  "cpuEvent"=>$cpuEvent,
  "cpuShot"=>["r"=>$cr,"c"=>$cc],
  "playerShots"=>$game["playerShots"],
  "cpuShots"=>$game["cpuShots"],
  "playerGrid"=>$game["playerGrid"],
  "status"=>"You fired: $playerEvent. Computer fired: $cpuEvent. Your turn."
]);
