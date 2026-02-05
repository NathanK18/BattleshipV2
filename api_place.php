<?php
header("Content-Type: application/json");
require_once __DIR__ . '/db.php';

const SIZE = 10;

// must match api_start.php
$shipsRequired = [
  5 => 1,
  3 => 1,
  2 => 1
];

function emptyGrid(): array {
  $g = [];
  for ($r = 0; $r < SIZE; $r++) $g[] = array_fill(0, SIZE, 0);
  return $g;
}

function inBounds(int $r, int $c): bool {
  return $r >= 0 && $r < SIZE && $c >= 0 && $c < SIZE;
}

function canPlace(array &$grid, int $r, int $c, int $len, int $dir): bool {
  if (!inBounds($r, $c)) return false;
  if ($len <= 0) return false;
  if ($dir !== 0 && $dir !== 1) return false;

  if ($dir === 0) {
    if ($c + $len > SIZE) return false;
    for ($i = 0; $i < $len; $i++) if ($grid[$r][$c + $i] !== 0) return false;
  } else {
    if ($r + $len > SIZE) return false;
    for ($i = 0; $i < $len; $i++) if ($grid[$r + $i][$c] !== 0) return false;
  }

  // no-touch rule (incl diagonals)
  $r2 = $dir ? ($r + $len - 1) : $r;
  $c2 = $dir ? $c : ($c + $len - 1);

  for ($rr = max(0, $r - 1); $rr <= min(SIZE - 1, $r2 + 1); $rr++) {
    for ($cc = max(0, $c - 1); $cc <= min(SIZE - 1, $c2 + 1); $cc++) {
      if ($grid[$rr][$cc] === 1) return false;
    }
  }
  return true;
}

function placeShip(array &$grid, int $r, int $c, int $len, int $dir): void {
  if ($dir === 0) for ($i = 0; $i < $len; $i++) $grid[$r][$c + $i] = 1;
  else           for ($i = 0; $i < $len; $i++) $grid[$r + $i][$c] = 1;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

$gameId = bs_get_game_id_from_request($input);
if (!$gameId) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'No gameId. Press Restart Game.']);
  exit;
}

try {
  $db = bs_db();
  $db->beginTransaction();

  $game = bs_load_game($gameId);
  if (!$game) {
    $db->rollBack();
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Game not found. Press Restart Game.']);
    exit;
  }

  if (($game['state'] ?? '') !== 'PLACING') {
    $db->rollBack();
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'Not in placement phase. Press Restart Game.']);
    exit;
  }

  $placements = $input['ships'] ?? null;
  if (!is_array($placements)) {
    $db->rollBack();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing ships array']);
    exit;
  }

  // Validate ship lengths + counts
  $need = $shipsRequired;
  foreach ($placements as $p) {
    $len = $p['len'] ?? null;
    if (!is_int($len) || !isset($need[$len]) || $need[$len] <= 0) {
      $db->rollBack();
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Invalid ship set']);
      exit;
    }
    $need[$len]--;
  }
  foreach ($need as $cnt) {
    if ($cnt !== 0) {
      $db->rollBack();
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Invalid ship set']);
      exit;
    }
  }

  // Validate placement geometry
  $grid = emptyGrid();
  foreach ($placements as $p) {
    $r = $p['r'] ?? null;
    $c = $p['c'] ?? null;
    $len = $p['len'] ?? null;
    $dir = $p['dir'] ?? null;

    if (!is_int($r) || !is_int($c) || !is_int($len) || !is_int($dir)) {
      $db->rollBack();
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Bad ship payload']);
      exit;
    }

    if (!canPlace($grid, $r, $c, $len, $dir)) {
      $db->rollBack();
      http_response_code(409);
      echo json_encode(['ok' => false, 'error' => 'Invalid placement (overlap/out of bounds/adjacent)']);
      exit;
    }

    placeShip($grid, $r, $c, $len, $dir);
  }

  $game['playerGrid'] = $grid;
  $game['state'] = 'PLAYER_TURN';

  bs_save_game($gameId, 'PLAYER_TURN', $game);
  $db->commit();

  echo json_encode([
    'ok' => true,
    'gameId' => $gameId,
    'state' => 'PLAYER_TURN',
    'playerGrid' => $game['playerGrid'],
    'playerShots' => $game['playerShots'],
    'cpuShots' => $game['cpuShots'],
    'status' => 'Fleet placed! Fire on the enemy board.'
  ]);
} catch (Throwable $e) {
  $pdo = bs_db();
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
