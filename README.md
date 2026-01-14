# PHP Craps - Multiplayer Casino Craps Game

A real-time multiplayer craps game with WebSocket support, built with PHP and deployed via Docker.

## Features

- **Real-Time Multiplayer**: WebSocket connections for instant game updates
- **Up to 8 Active Players**: Multiple players can join and bet simultaneously
- **Spectator Mode**: Unlimited spectators can watch the action
- **Authentic Casino Rules**: Full Vegas-style craps implementation
- **Timer System**: 20-second shooter countdown with 15-second betting window
- **Multiple Bet Types**:
  - Pass Line / Don't Pass (1:1)
  - Come / Don't Come (1:1)
  - Field Bets (1:1, 2:1 on 2/12)
  - Place Bets (4/10: 9:5, 5/9: 7:5, 6/8: 7:6)
  - Hardways (Hard 4/10: 7:1, Hard 6/8: 9:1)
  - Proposition Bets (Any Seven: 4:1, Any Craps: 7:1)
  - Pass Line Odds (3x-4x-5x system with true odds)
- **Bankroll Management**: Players start with $1000 in chips
- **Casino-Style Interface**: Authentic felt table with visual chip placement

## Requirements

- Docker Desktop or Docker Engine
- Docker Compose

That's it! Everything else is containerized.

## Quick Start

### 1. Clone the Repository

```bash
git clone <repository-url>
cd phpcraps
```

### 2. Start the Game

```bash
docker-compose up -d
```

This will:
- Build the PHP container with all dependencies
- Set up the SQLite database automatically
- Start the WebSocket server for real-time updates
- Launch Caddy web server on port 8000

### 3. Play

Open your browser to:
```
http://localhost:8000
```

### Stopping the Game

```bash
docker-compose down
```

## Docker Architecture

```
┌─────────────────────────────────────────┐
│   Browser (localhost:8000)              │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│   Caddy Container (Port 8000)           │
│   - Serves static files (HTML/CSS/JS)  │
│   - Proxies PHP requests → PHP-FPM      │
│   - Proxies WebSocket → WS Server       │
└────┬─────────────────────────┬──────────┘
     │                         │
     ▼                         ▼
┌──────────────────┐  ┌─────────────────┐
│  PHP Container   │  │  PHP Container  │
│  (PHP-FPM:9000)  │  │  (WS:8080)      │
│  - Game Logic    │  │  - Real-time    │
│  - Bet System    │  │    Updates      │
│  - SQLite DB     │  │  - Broadcasts   │
└──────────────────┘  └─────────────────┘
```

Both PHP-FPM and WebSocket server run in the same container, managed by Supervisor.

## How to Play

### Joining the Game

1. Open http://localhost:8000
2. Enter your name
3. Choose **Play** (can bet and roll) or **Watch** (spectator mode)
4. Click "Join Table"

### Placing Bets

1. **Select a chip value** from the chip tray at the bottom
2. **Click on a bet area** on the craps table to place that chip amount
3. You can place multiple bets of different amounts
4. **Betting window**: Non-shooters have 15 seconds to place bets before the roll
5. **Shooters** can place bets any time during their 20-second turn

### Betting Rules

- **Cannot bet on the point**: When a point is established, you cannot place bets directly on that number
- **Use Pass Line + Odds**: To bet on the point, use Pass Line and then add Odds bets (up to 3x-4x-5x)
- **Place bets stay active**: When place bets win, you get paid but the bet stays on the table
- **Hardways**: Only clear when hit (win), hit the easy way (lose), or seven-out (lose)

### Rolling the Dice

- Only the **shooter** can roll
- You have **20 seconds** to roll when it's your turn
- Click the "ROLL DICE" button
- After a seven-out, the dice pass to the next player

### Game Rules

#### Come Out Roll (First Roll)
- **7 or 11**: Natural win - Pass Line wins
- **2, 3, or 12**: Craps - Pass Line loses (12 pushes Don't Pass)
- **4, 5, 6, 8, 9, 10**: Establishes the Point

#### Point Phase
- **Roll the Point**: Point made - Pass Line wins, game resets to Come Out
- **Roll a 7**: Seven-out - Pass Line loses, shooter rotates
- **Other numbers**: Keep rolling

## Project Structure

```
phpcraps/
├── docker/
│   └── supervisord.conf        # PHP-FPM + WebSocket process manager
├── Dockerfile                  # Container definition
├── docker-compose.yml          # Service orchestration
├── Caddyfile                   # Web server configuration
├── database/
│   ├── schema.sql              # Database schema
│   ├── setup.php               # Database initialization
│   ├── migrate.php             # Schema migrations
│   └── craps_game.db          # SQLite database (auto-created)
├── server/
│   └── websocket-server.php    # WebSocket server
├── src/
│   ├── Database/
│   │   └── Database.php       # Database operations
│   └── Game/
│       ├── Bet.php            # Bet types and payouts
│       ├── CrapsGame.php      # Core game rules
│       ├── Dice.php           # Dice rolling
│       ├── GameManager.php    # Multi-player coordination
│       └── Player.php         # Player management
├── public/
│   ├── css/
│   │   └── style.css          # Casino-style UI
│   ├── js/
│   │   └── game.js            # Frontend with WebSocket support
│   ├── api.php                # REST API (WebSocket fallback)
│   └── index.html             # Main game interface
└── README.md
```

## Development

### View Logs

```bash
# All logs
docker-compose logs -f

# PHP container only
docker-compose logs -f php

# Caddy only
docker-compose logs -f caddy
```

### Rebuild After Code Changes

```bash
docker-compose down
docker-compose up -d --build
```

### Access Container Shell

```bash
docker-compose exec php sh
```

### Reset the Game

```bash
docker-compose exec php php /var/www/html/reset-game.sh
```

Or manually:
```bash
docker-compose exec php sqlite3 /var/www/html/database/craps_game.db "UPDATE players SET is_active = 0; UPDATE games SET shooter_id = NULL, phase = 'come_out', point = NULL;"
```

### Test Bet Payouts

```bash
docker-compose exec php php /var/www/html/test-bets.php
```

## Configuration

### Change Max Players

Edit `src/Game/GameManager.php`:
```php
private const MAX_PLAYERS = 8;  // Change this value
```

### Change Starting Bankroll

Edit `src/Database/Database.php` in the `addPlayer()` method:
```php
$bankroll = 1000.0;  // Change default value
```

### Change Timer Durations

Edit `src/Game/GameManager.php`:
```php
// Shooter timeout
if ($elapsed >= 20) {  // Change 20 to desired seconds

// Betting window
$bettingTimeRemaining = max(0, 15 - $elapsed);  // Change 15
```

## Troubleshooting

### Port Already in Use

If port 8000 or 8080 is in use:

```bash
# Find what's using the port
lsof -i :8000
lsof -i :8080

# Kill the process
kill -9 <PID>

# Or change ports in docker-compose.yml
```

### WebSocket Not Connecting

Check if the WebSocket server is running:
```bash
docker-compose logs php | grep WebSocket
```

Should see: `WebSocket server started on 0.0.0.0:8080`

### Database Errors

Reset the database:
```bash
docker-compose down
rm database/craps_game.db
docker-compose up -d --build
```

### Players Not Updating

1. Check browser console (F12) for errors
2. Verify WebSocket connection status
3. Restart containers: `docker-compose restart`

### Can't Roll Dice

Make sure:
1. You joined as a player (not spectator)
2. You're the current shooter (name shows under "Shooter:")
3. The 20-second timer hasn't expired

### Rebuild Everything from Scratch

```bash
docker-compose down -v
docker system prune -a
docker-compose up -d --build
```

## API Reference

### WebSocket Messages

**Client → Server:**
```json
{"action": "join", "name": "Player1", "role": "player"}
{"action": "bet", "player_id": 123, "bet_type": "pass_line", "amount": 10}
{"action": "roll", "player_id": 123}
{"action": "ping"}
```

**Server → Client:**
```json
{"type": "game_state", "data": {...}}
{"type": "join_result", "data": {...}}
{"type": "bet_result", "data": {...}}
{"type": "roll_result", "data": {...}}
{"type": "error", "message": "..."}
{"type": "pong"}
```

### REST API (Fallback)

- `POST /api.php?action=join` - Join game
- `POST /api.php?action=bet` - Place bet
- `POST /api.php?action=roll` - Roll dice
- `GET /api.php?action=state` - Get game state
- `GET /api.php?action=player&player_id=X` - Get player info

## Technologies

- **Backend**: PHP 8.2 with OOP architecture
- **Real-time**: Native PHP WebSocket server
- **Database**: SQLite with PDO
- **Frontend**: Vanilla JavaScript (no frameworks)
- **Web Server**: Caddy 2
- **Deployment**: Docker + Docker Compose
- **Process Manager**: Supervisor

## License

Open source - available for educational purposes.

## Contributing

Issues and pull requests welcome!
