#!/bin/bash

# Debug script for PHP Craps game

echo "🎲 PHP Craps Game Debug Information"
echo "===================================="
echo ""

echo "📊 Game State:"
sqlite3 database/craps_game.db "SELECT 'Status: ' || status, 'Phase: ' || phase, 'Point: ' || COALESCE(point, 'OFF'), 'Shooter ID: ' || COALESCE(shooter_id, 'NONE') FROM games WHERE id = 1;"
echo ""

echo "👥 Active Players:"
sqlite3 database/craps_game.db "SELECT id, name, role, bankroll, datetime(last_active, 'localtime') as last_active_local FROM players WHERE is_active = 1;"
echo ""

echo "💤 Recently Inactive Players (last 5):"
sqlite3 database/craps_game.db "SELECT id, name, role, datetime(last_active, 'localtime') as last_active_local FROM players WHERE is_active = 0 ORDER BY last_active DESC LIMIT 5;"
echo ""

echo "🎲 Recent Rolls (last 5):"
sqlite3 database/craps_game.db "SELECT p.name, r.die1 || ' + ' || r.die2 || ' = ' || r.total as roll, r.phase, datetime(r.rolled_at, 'localtime') as rolled_local FROM rolls r JOIN players p ON r.player_id = p.id ORDER BY r.rolled_at DESC LIMIT 5;"
echo ""

echo "💰 Active Bets:"
sqlite3 database/craps_game.db "SELECT p.name, b.bet_type, b.amount, b.status FROM bets b JOIN players p ON b.player_id = p.id WHERE b.status = 'active';"
echo ""

echo "🔧 Quick Fixes:"
echo "  Reset game: sqlite3 database/craps_game.db \"UPDATE players SET is_active = 0; UPDATE games SET shooter_id = NULL;\""
echo "  Clean inactive: php -r \"require 'autoload.php'; \\\$db = new Craps\\\\Database\\\\Database('database/craps_game.db'); \\\$gm = new Craps\\\\Game\\\\GameManager(\\\$db); \\\$gm->cleanupInactivePlayers();\""
