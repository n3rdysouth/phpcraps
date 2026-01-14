#!/bin/bash

# Quick reset script for PHP Craps game

echo "🎲 Resetting Craps Game"
echo ""

# Deactivate all players
sqlite3 database/craps_game.db "UPDATE players SET is_active = 0;"

# Clear shooter
sqlite3 database/craps_game.db "UPDATE games SET shooter_id = NULL WHERE id = 1;"

# Reset game to come out phase
sqlite3 database/craps_game.db "UPDATE games SET phase = 'come_out', point = NULL WHERE id = 1;"

# Optional: Clear all bets (uncomment if you want this)
# sqlite3 database/craps_game.db "UPDATE bets SET status = 'lost' WHERE status = 'active';"

echo "✅ Game reset complete!"
echo ""
echo "Next player to join will become the shooter."
echo "All previous players have been deactivated."
echo ""
