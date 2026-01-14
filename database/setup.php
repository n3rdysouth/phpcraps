<?php
/**
 * Database setup script
 * Run this file to create the SQLite database and tables
 */

$dbPath = __DIR__ . '/craps_game.db';
$schemaPath = __DIR__ . '/schema.sql';

try {
    // Create SQLite database
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read and execute schema
    $schema = file_get_contents($schemaPath);
    $db->exec($schema);

    // Create a default game
    $db->exec("INSERT INTO games (status, phase) VALUES ('waiting', 'come_out')");

    echo "Database setup complete!\n";
    echo "Database created at: $dbPath\n";
    echo "Default game created with ID: 1\n";

} catch (Exception $e) {
    echo "Error setting up database: " . $e->getMessage() . "\n";
    exit(1);
}
