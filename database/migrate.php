<?php

/**
 * Database migration script - adds last_active column to players table
 */

require_once __DIR__ . '/../config/config.php';

$config = require __DIR__ . '/../config/config.php';
$dbPath = $config['database']['path'];

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Running database migration...\n";

    // Check if last_active column exists
    $result = $pdo->query("PRAGMA table_info(players)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);

    $hasLastActive = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'last_active') {
            $hasLastActive = true;
            break;
        }
    }

    if (!$hasLastActive) {
        echo "Adding last_active column to players table...\n";
        // SQLite doesn't support CURRENT_TIMESTAMP in ALTER TABLE, so add without default
        $pdo->exec("ALTER TABLE players ADD COLUMN last_active DATETIME");

        // Update existing players with current timestamp
        $pdo->exec("UPDATE players SET last_active = datetime('now')");

        echo "✓ last_active column added!\n";
    }

    // Check if point_number column exists in bets table
    $result = $pdo->query("PRAGMA table_info(bets)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);

    $hasPointNumber = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'point_number') {
            $hasPointNumber = true;
            break;
        }
    }

    if (!$hasPointNumber) {
        echo "Adding point_number column to bets table...\n";
        $pdo->exec("ALTER TABLE bets ADD COLUMN point_number INTEGER DEFAULT NULL");
        echo "✓ point_number column added!\n";
    }

    // Check if turn_started_at column exists in games table
    $result = $pdo->query("PRAGMA table_info(games)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);

    $hasTurnStartedAt = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'turn_started_at') {
            $hasTurnStartedAt = true;
            break;
        }
    }

    if (!$hasTurnStartedAt) {
        echo "Adding turn_started_at column to games table...\n";
        $pdo->exec("ALTER TABLE games ADD COLUMN turn_started_at DATETIME DEFAULT NULL");
        // Initialize existing games with current timestamp
        $pdo->exec("UPDATE games SET turn_started_at = CURRENT_TIMESTAMP WHERE shooter_id IS NOT NULL");
        echo "✓ turn_started_at column added!\n";
    }

    echo "✓ Database is up to date.\n";

} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
