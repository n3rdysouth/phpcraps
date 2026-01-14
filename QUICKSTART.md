# PHP Craps - Quick Start Guide

## 🚀 One Command Setup

```bash
docker-compose up
```

That's it! Open **http://localhost:8000** in your browser.

## What's Running

- **Web Server (Caddy)**: Port 8000
  - Serves the game interface
  - Proxies PHP requests to PHP-FPM
  - Proxies WebSocket connections for real-time updates

- **PHP Container**:
  - PHP-FPM for game logic
  - WebSocket server for real-time multiplayer
  - SQLite database (auto-initialized)

## How to Play

1. Open http://localhost:8000
2. Enter your name
3. Choose "Play" to join as a player
4. Select chip values and click bet areas to place bets
5. When it's your turn as shooter, click "ROLL DICE"

## Common Commands

```bash
# Start everything
docker-compose up

# Start in background
docker-compose up -d

# View logs
docker-compose logs -f

# Stop everything
docker-compose down

# Restart after code changes
docker-compose restart

# Reset database
docker-compose down -v
docker-compose up
```

## Troubleshooting

### Port 8000 already in use?
Edit `docker-compose.yml` and change:
```yaml
ports:
  - "8001:8000"  # Use 8001 instead
```

### Need to reset the game?
```bash
docker-compose exec php sqlite3 database/craps_game.db "UPDATE players SET is_active = 0; UPDATE games SET shooter_id = NULL;"
```

### Check if services are running:
```bash
docker-compose ps
```

### View logs for specific service:
```bash
docker-compose logs -f php    # PHP logs
docker-compose logs -f caddy  # Web server logs
```

## Architecture

```
Browser (localhost:8000)
    ↓
Caddy Container
    ├── Static files (HTML/CSS/JS)
    ├→ PHP-FPM (game logic)
    └→ WebSocket Server (real-time updates)
```

All running in Docker, no local PHP/Caddy installation needed!

##Features

✅ Real-time multiplayer (up to 8 players)
✅ WebSocket support with auto-fallback to polling
✅ All craps bet types (Pass Line, Field, Hardways, Place Bets, Prop Bets)
✅ Automatic inactive player cleanup (60 second timeout)
✅ Casino-style interface with visual chip placement
✅ Persistent SQLite database

## Development

### Edit code
Files are mounted from your local directory - just edit and refresh!

### Rebuild after dependency changes
```bash
docker-compose up --build
```

### Access container shell
```bash
docker-compose exec php sh
```

## No Docker?

See the full README.md for manual installation instructions.
