#!/bin/bash

# Development startup script for PHP Craps game

echo "🎲 Starting PHP Craps Game Development Environment"
echo ""

# Check if database exists
if [ ! -f "database/craps_game.db" ]; then
    echo "📦 Database not found. Running setup..."
    php database/setup.php
    echo ""
fi

# Run migration
echo "🔄 Running database migrations..."
php database/migrate.php
echo ""

# Start WebSocket server in background
echo "🌐 Starting WebSocket server on port 8080..."
php server/websocket-server.php &
WS_PID=$!
echo "WebSocket server PID: $WS_PID"
echo ""

# Give WebSocket server time to start
sleep 2

# Start PHP development server
echo "🚀 Starting PHP development server on http://localhost:8000..."
echo ""
echo "✨ Game is ready! Open http://localhost:8000 in your browser"
echo ""
echo "Press Ctrl+C to stop all servers"
echo ""

cd public
php -S localhost:8000

# Cleanup: kill WebSocket server when PHP server stops
kill $WS_PID 2>/dev/null
echo ""
echo "👋 Servers stopped"
