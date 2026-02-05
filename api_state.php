<?php
header("Content-Type: application/json");
require_once __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

$gameId = bs_get_game_id_from_request($input);
if (!$gameId) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'No gameId. Press Restart Game.']);
  exit;
}

$game = bs_load_game($gameId);
if (!$game) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'Game not found. Press Restart Game.']);
  exit;
}

echo json_encode([
  'ok' => true,
  'gameId' => $gameId,
  'size' => $game['size'] ?? 10,
  'state' => $game['state'] ?? 'PLACING',
  'shipsToPlace' => $game['shipsToPlace'] ?? [],
  'playerGrid' => $game['playerGrid'] ?? [],
  'playerShots' => $game['playerShots'] ?? [],
  'cpuShots' => $game['cpuShots'] ?? [],
  'status' => ($game['state'] ?? '') === 'PLACING'
    ? 'Resume: place your fleet. Press R to rotate.'
    : (($game['state'] ?? '') === 'GAME_OVER'
        ? 'Resume: game over. Press Restart Game.'
        : 'Resume: keep playing!')
]);
