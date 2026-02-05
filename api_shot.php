<?php
header("Content-Type: application/json");
require_once __DIR__ . '/db.php';

const SIZE = 10;

function inBounds(int $r, int $c): bool {
  return $r >= 0 && $r < SIZE && $c >= 0 && $c < SIZE;
}

function addTargets(array &$game, int $r, int $c): void {
  $cands = [[ $r - 1, $c ], [ $r + 1, $c ], [ $r, $c - 1 ], [ $r, $c + 1 ]];
  foreach ($cands as $p) {
    if (!inBounds($p[0], $p[1])) continue;
    $game['aiTargets'][] = $p;
  }
}

function aiPickShot(array &$game): array {
  while (!empty($game['aiTargets'])) {
    $p = array_shift($game['aiTargets']);
    [$r, $c] = $p;
    if ($game['cpuShots'][$r][$c] === 0) return [$r, $c];
  }

  do {
    $r = random_int(0, SIZE - 1);
    $c = random_int(0, SIZE - 1);
  } while ($game['cpuShots'][$r][$c] !== 0);

  return [$r, $c];
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

$gameId = bs_get_game_id_from_request($input);
if (!$gameId) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'No gameId. Press Restart Game.']);
  exit;
}

$r = $input['r'] ?? null;
$c = $input['c'] ?? null;

if (!is_int($r) || !is_int($c) || !inBounds($r, $c)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid shot coordinates']);
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

  if (($game['state'] ?? '') !== 'PLAYER_TURN') {
    $db->rollBack();
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'Not your turn / not ready yet.']);
    exit;
  }

  // Player shoots CPU
  if ($game['playerShots'][$r][$c] !== 0) {
    $db->rollBack();
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'You already shot there']);
    exit;
  }

  $playerEvent = 'miss';
  if ($game['cpuGrid'][$r][$c] === 1) {
    $game['playerShots'][$r][$c] = 2;
    $game['playerHits']++;
    $playerEvent = 'hit';
  } else {
    $game['playerShots'][$r][$c] = 1;
  }

  $total = (int)($game['totalShipCells'] ?? 0);

  if ($game['playerHits'] >= $total) {
    $game['state'] = 'GAME_OVER';
    bs_save_game($gameId, 'GAME_OVER', $game);
    $db->commit();

    echo json_encode([
      'ok' => true,
      'gameId' => $gameId,
      'size' => $game['size'] ?? SIZE,
      'state' => 'GAME_OVER',
      'playerGrid' => $game['playerGrid'],
      'playerShots' => $game['playerShots'],
      'cpuShots' => $game['cpuShots'],
      'status' => 'You win! 🎉 Press Restart Game to play again.'
    ]);
    exit;
  }

  // CPU turn
  $game['state'] = 'CPU_TURN';
  [$cr, $cc] = aiPickShot($game);

  $cpuEvent = 'miss';
  if ($game['playerGrid'][$cr][$cc] === 1) {
    $game['cpuShots'][$cr][$cc] = 2;
    $game['cpuHits']++;
    $cpuEvent = 'hit';
    addTargets($game, $cr, $cc);
  } else {
    $game['cpuShots'][$cr][$cc] = 1;
  }

  if ($game['cpuHits'] >= $total) {
    $game['state'] = 'GAME_OVER';
    bs_save_game($gameId, 'GAME_OVER', $game);
    $db->commit();

    echo json_encode([
      'ok' => true,
      'gameId' => $gameId,
      'size' => $game['size'] ?? SIZE,
      'state' => 'GAME_OVER',
      'playerGrid' => $game['playerGrid'],
      'playerShots' => $game['playerShots'],
      'cpuShots' => $game['cpuShots'],
      'status' => 'Computer wins! 💥 Press Restart Game to play again.'
    ]);
    exit;
  }

  $game['state'] = 'PLAYER_TURN';
  bs_save_game($gameId, 'PLAYER_TURN', $game);
  $db->commit();

  echo json_encode([
    'ok' => true,
    'gameId' => $gameId,
    'size' => $game['size'] ?? SIZE,
    'state' => 'PLAYER_TURN',
    'playerGrid' => $game['playerGrid'],
    'playerShots' => $game['playerShots'],
    'cpuShots' => $game['cpuShots'],
    'status' => "You fired: $playerEvent. Computer fired: $cpuEvent. Your turn."
  ]);
} catch (Throwable $e) {
  $pdo = bs_db();
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
