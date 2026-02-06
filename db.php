<?php
// SQLite persistence helpers for Battleship.
// Goal: "download and run" with minimal setup, even on XAMPP/macOS permission quirks.

function bs_db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  // Candidate data directories (first writable wins).
  // 1) Project-local folder (nice if Apache can write there)
  // 2) /tmp (best universal fallback for XAMPP/macOS)
  // 3) XAMPP temp (often writable by daemon)
  // 4) PHP system temp (may be per-user and not writable by Apache user)
  $candidates = [
    __DIR__ . DIRECTORY_SEPARATOR . 'data',
    '/tmp/battleshipv2',
    // Relative path from htdocs/<project>/db.php -> xamppfiles/temp
    realpath(__DIR__ . '/../../temp') ? (realpath(__DIR__ . '/../../temp') . '/battleshipv2') : null,
    '/Applications/XAMPP/xamppfiles/temp/battleshipv2',
    sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'battleshipv2',
  ];

  // Remove nulls
  $candidates = array_values(array_filter($candidates, fn($x) => is_string($x) && $x !== ''));

  $dataDir = null;

  foreach ($candidates as $dir) {
    // Try to create the directory if it doesn't exist (suppress warnings)
    if (!is_dir($dir)) {
      @mkdir($dir, 0775, true);
    }

    if (is_dir($dir) && is_writable($dir)) {
      $dataDir = $dir;
      break;
    }
  }

  if ($dataDir === null) {
    throw new Exception("No writable data directory found. Tried: " . implode(", ", $candidates));
  }

  $path = $dataDir . DIRECTORY_SEPARATOR . 'battleship.db';

  // Create/open the sqlite database
  $pdo = new PDO('sqlite:' . $path);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  // Initialize schema
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

  // Ensure canonical state is present
  $game['state'] = $row['state'];
  return $game;
}

function bs_save_game(string $gameId, string $state, array $game): void {
  $game['state'] = $state;

  $json = json_encode($game);
  if ($json === false) {
    throw new Exception('Failed to encode game JSON');
  }

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
