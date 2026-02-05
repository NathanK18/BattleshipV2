<?php
// SQLite persistence helpers for Battleship.

function bs_db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';

  if (!is_dir($dataDir)) {
    // Suppress mkdir warnings so APIs still return valid JSON.
    if (!@mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
      $err = error_get_last();
      $msg = $err ? $err['message'] : 'unknown error';
      throw new Exception("Cannot create data directory: $dataDir ($msg)");
    }
  }

  if (!is_writable($dataDir)) {
    throw new Exception("Data directory is not writable by PHP: $dataDir");
  }

  $path = $dataDir . DIRECTORY_SEPARATOR . 'battleship.db';

  // This will throw a clean exception if SQLite driver isn't installed/enabled.
  $pdo = new PDO('sqlite:' . $path);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS games (
      game_id TEXT PRIMARY KEY,
      state TEXT NOT NULL,
      data TEXT NOT NULL,
      updated_at INTEGER NOT NULL
    )"
  );

  return $pdo;
}

function bs_new_game_id(): string {
  return bin2hex(random_bytes(16));
}

function bs_load_game(string $gameId): ?array {
  $stmt = bs_db()->prepare('SELECT state, data FROM games WHERE game_id = ?');
  $stmt->execute([$gameId]);
  $row = $stmt->fetch();
  if (!$row) return null;

  $game = json_decode($row['data'], true);
  if (!is_array($game)) return null;

  $game['state'] = $row['state'];
  return $game;
}

function bs_save_game(string $gameId, string $state, array $game): void {
  $game['state'] = $state;
  $json = json_encode($game);
  if ($json === false) throw new Exception('Failed to encode game JSON');

  $now = time();
  $stmt = bs_db()->prepare(
    'INSERT INTO games(game_id, state, data, updated_at) VALUES(?,?,?,?)
     ON CONFLICT(game_id) DO UPDATE SET
       state=excluded.state,
       data=excluded.data,
       updated_at=excluded.updated_at'
  );
  $stmt->execute([$gameId, $state, $json, $now]);
}

function bs_get_game_id_from_request(array $input): ?string {
  $gid = $input['gameId'] ?? null;
  if (is_string($gid) && $gid !== '') return $gid;

  if (isset($_COOKIE['game_id']) && is_string($_COOKIE['game_id']) && $_COOKIE['game_id'] !== '') {
    return $_COOKIE['game_id'];
  }
  return null;
}
