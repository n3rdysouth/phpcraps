# PHP Craps - Multiplayer Craps Game

A multiplayer craps game built with PHP, featuring real-time gameplay where multiple users can join as players or spectators.

## Features

- **Real-time Multiplayer**: WebSocket support for instant game updates (with AJAX polling fallback)
- **Multiplayer Support**: Up to 8 active players can play simultaneously
- **Automatic Player Cleanup**: Inactive players are removed after 60 seconds to free up slots
- **Spectator Mode**: Unlimited spectators can watch the game
- **Full Craps Rules**:
  - Come Out Roll and Point phases
  - Natural wins (7, 11) and Craps (2, 3, 12)
  - Point establishment and resolution
  - Shooter rotation on seven-out
- **Multiple Bet Types**:
  - Pass Line / Don't Pass (1:1 payout)
  - Come / Don't Come (1:1 payout)
  - Field (variable payout: 2x for 2/12, 1x for 3,4,9,10,11)
  - Hardways (Hard 4/10: 7:1, Hard 6/8: 9:1)
  - Place Bets (4/10: 9:5, 5/9: 7:5, 6/8: 7:6)
  - Proposition Bets (Any Seven: 4:1, Any Craps: 7:1)
- **Real-time Updates**: WebSocket connections with automatic fallback to AJAX polling
- **Bankroll Management**: Players start with $1000 in chips
- **Casino-style Interface**: Authentic craps table with visual chip placement

## Requirements

### Docker (Recommended - Easiest Setup)
- Docker Desktop or Docker Engine
- Docker Compose

### Manual Installation (Alternative)
- PHP 7.4 or higher (with socket extension enabled)
- SQLite support (usually included with PHP)
- Web server (Caddy recommended, or Apache/Nginx/PHP built-in server)
- For WebSocket support: PHP CLI for running the WebSocket server

## Quick Start with Docker (Recommended)

### 1. Clone or Download

Clone this repository or download the files to your local machine.

### 2. Set Up Database

Run the database setup script to create the SQLite database and tables:

```bash
php database/setup.php
```

This will create:
- `database/craps_game.db` - SQLite database file
- All necessary tables (games, players, bets, rolls)
- A default game with ID 1

### 3. Start the Servers (Manual Installation Only)

> **Note:** If using Docker, skip this section - everything starts automatically!

#### Option A: Quick Development Start (Recommended for Manual Setup)

```bash
./start-dev.sh
```

This script:
- Checks database and runs migrations
- Starts WebSocket server on port 8080
- Starts PHP dev server on port 8000
- Press Ctrl+C to stop both servers

#### Option B: Caddy (Recommended for Production)

Caddy provides the best experience with automatic HTTPS and WebSocket support.

1. Install Caddy (if not already installed):
   ```bash
   # macOS
   brew install caddy

   # Linux
   curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
   curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
   sudo apt update
   sudo apt install caddy
   ```

2. Start the PHP WebSocket server (in one terminal):
   ```bash
   php server/websocket-server.php
   ```

3. Start Caddy (in another terminal):
   ```bash
   caddy run
   ```

4. Open your browser to:
   ```
   http://localhost:8000
   ```

**Note:** Make sure PHP-FPM is running. On macOS with Homebrew:
```bash
brew services start php
```

#### Option C: Manual Start (WebSocket + PHP Dev Server)

Start servers in separate terminals:

```bash
# Terminal 1: WebSocket server
php server/websocket-server.php

# Terminal 2: PHP dev server
cd public && php -S localhost:8000
```

#### Option D: PHP Built-in Server Only (No WebSockets)

For quick testing without real-time updates:

```bash
cd public
php -S localhost:8000
```

The game will automatically fall back to AJAX polling (updates every 2 seconds).

#### Option E: Apache/Nginx

Configure your web server to serve from the `public/` directory. For WebSocket support, you'll need to configure a reverse proxy to `localhost:8080` for WebSocket connections.

### 4. Open in Browser

Navigate to:
```
http://localhost:8000
```

## How to Play

### Joining the Game

1. Open the game in your browser
2. Enter your name
3. Choose to join as a **Player** (can bet and play) or **Spectator** (watch only)
4. Click "Join Game"

### Placing Bets

1. Select a bet type by clicking on one of the bet options:
   - **Pass Line**: Bet with the shooter
   - **Don't Pass**: Bet against the shooter
   - **Field**: One-roll bet on 2,3,4,9,10,11,12
   - **Come**: Similar to Pass Line during Point phase
2. Enter your bet amount
3. Click "Place Bet"

### Rolling the Dice

- Only the **shooter** (current active player) can roll the dice
- Click the "Roll Dice" button when it's your turn
- The shooter rotates to the next player after a "seven-out"

### Game Rules

#### Come Out Roll (First Roll)
- **7 or 11**: Natural win - Pass Line wins, Don't Pass loses
- **2, 3, or 12**: Craps - Pass Line loses, Don't Pass wins (12 is push for Don't Pass)
- **4, 5, 6, 8, 9, 10**: Establishes the Point

#### Point Phase
- **Roll the Point number**: Point made - Pass Line wins, Don't Pass loses
- **Roll a 7**: Seven out - Pass Line loses, Don't Pass wins, shooter rotates
- **Any other number**: No decision, keep rolling

#### Field Bet (One Roll)
- **Wins on**: 2, 3, 4, 9, 10, 11, 12
- **Loses on**: 5, 6, 7, 8
- **Payout**: 2:1 on 2 or 12, 1:1 on others

## Docker Architecture

When using Docker, the setup includes:

```
┌─────────────────────────────────────────┐
│   Browser (localhost:8000)              │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│   Caddy Container (Port 8000)           │
│   - Serves static files                 │
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
└──────────────────┘  └─────────────────┘
```

## Project Structure

```
phpcraps/
├── docker/
│   └── supervisord.conf        # Process manager config
├── Dockerfile                  # PHP container definition
├── docker-compose.yml          # Service orchestration
├── config/
│   └── config.php              # Configuration settings
├── database/
│   ├── schema.sql              # Database schema
│   ├── setup.php               # Database initialization script
│   ├── migrate.php             # Database migration script
│   └── craps_game.db          # SQLite database (created after setup)
├── server/
│   └── websocket-server.php    # WebSocket server for real-time updates
├── src/
│   ├── Database/
│   │   └── Database.php       # Database operations
│   └── Game/
│       ├── Bet.php            # Bet class and logic
│       ├── CrapsGame.php      # Core game rules and logic
│       ├── Dice.php           # Dice rolling
│       ├── GameManager.php    # Multi-player coordination
│       └── Player.php         # Player management
├── public/
│   ├── css/
│   │   └── style.css          # Casino-style game styling
│   ├── js/
│   │   └── game.js            # Frontend with WebSocket support
│   ├── api.php                # REST API endpoints (fallback)
│   └── index.html             # Main game interface
├── autoload.php               # PSR-4 autoloader
├── Caddyfile                  # Caddy web server configuration
├── composer.json              # Composer configuration
└── README.md                  # This file
```

## API Endpoints

### WebSocket API (Primary - Port 8080)

Real-time bidirectional communication:

**Client → Server:**
- `{action: "register", player_id: 123}` - Register player with connection
- `{action: "join", name: "Player", role: "player"}` - Join game
- `{action: "bet", player_id: 123, bet_type: "pass_line", amount: 10}` - Place bet
- `{action: "roll", player_id: 123}` - Roll dice
- `{action: "ping"}` - Keepalive ping

**Server → Client:**
- `{type: "game_state", data: {...}}` - Broadcast game state updates
- `{type: "join_result", data: {...}}` - Join response
- `{type: "bet_result", data: {...}}` - Bet response
- `{type: "roll_result", data: {...}}` - Roll result
- `{type: "error", message: "..."}` - Error message
- `{type: "pong"}` - Keepalive response

### REST API (Fallback)

HTTP endpoints when WebSockets are unavailable:

- `POST /api.php?action=join` - Join game as player or spectator
- `POST /api.php?action=bet` - Place a bet
- `POST /api.php?action=roll` - Roll the dice (shooter only)
- `GET /api.php?action=state` - Get current game state
- `GET /api.php?action=player` - Get player info and active bets

## Customization

### Maximum Players

Edit `config/config.php` to change the maximum number of players:

```php
'max_players' => 8,  // Change this value
```

### Starting Bankroll

Change the starting chip amount in `config/config.php`:

```php
'starting_bankroll' => 1000.0,  // Change this value
```

### WebSocket / Polling Configuration

The game automatically uses WebSockets when available and falls back to AJAX polling. To disable WebSockets, edit `public/js/game.js`:

```javascript
this.useWebSocket = false; // Change from true to false
```

To change the polling interval for the fallback mode:

```javascript
// Change 2000 to desired milliseconds
this.pollingInterval = setInterval(() => {
    this.updateGameState();
    this.updatePlayerInfo();
}, 2000);  // Currently 2 seconds
```

### Player Inactivity Timeout

Players are automatically removed after 60 seconds of inactivity. To change this, edit the cleanup call in `src/Game/GameManager.php`:

```php
$this->cleanupInactivePlayers(60); // Change 60 to desired seconds
```

## Technologies Used

- **Backend**: PHP 7.4+ with OOP architecture
- **Real-time Communication**: WebSocket server using PHP's native socket functions
- **Database**: SQLite with PDO
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Web Server**: Caddy (recommended) with automatic HTTPS
- **Architecture**: WebSocket-first with REST API fallback

## Development

### Testing Bet Payouts

Run the test suite to verify all bet payouts are correct:

```bash
php test-bets.php
```

This tests all bet types (Pass Line, Field, Hardways, Place Bets, Proposition Bets) to ensure payouts match the expected odds.

### Adding New Bet Types

1. Add bet type constant to `src/Game/Bet.php`
2. Add payout calculation in `Bet::calculatePayout()`
3. Add bet resolution logic in `GameManager::resolveBets()`
4. Add bet option to `public/index.html`
5. Add test case to `test-bets.php`

### Extending Game Logic

The core game logic is in `src/Game/CrapsGame.php`. Modify the `processComeOutRoll()` and `processPointRoll()` methods to add new rules.

### Debugging Bet Resolution

Check PHP error logs to see detailed payout information:
```bash
tail -f /var/log/php/error.log
```

Each resolved bet logs:
- Player ID
- Bet type
- Original amount
- Win/Loss status
- Payout amount

## Helper Scripts

### Quick Reset

If you need to clear all players and reset the game:

```bash
./reset-game.sh
```

This will:
- Deactivate all players
- Clear the shooter
- Reset to come out phase
- Next player to join becomes shooter

### Debug Information

View current game state and players:

```bash
./debug-game.sh
```

Shows:
- Game phase and point
- Active players
- Recent rolls
- Active bets

## Troubleshooting

### WebSocket won't connect

Don't worry! The game automatically falls back to polling. But if you want WebSockets:

1. Make sure the WebSocket server is running:
   ```bash
   php server/websocket-server.php
   ```
2. Check if port 8080 is available:
   ```bash
   lsof -i :8080
   ```
3. The browser console will show "WebSocket unavailable, falling back to polling" - this is normal if the server isn't running

### Database errors

If you get database errors, make sure:
1. The `database/` directory is writable
2. You ran `php database/setup.php`
3. SQLite extension is enabled in PHP
4. Run `php database/migrate.php` to apply schema updates

### WebSocket connection fails

If WebSockets aren't working:
1. Verify the WebSocket server is running: `php server/websocket-server.php`
2. Check port 8080 is not blocked by firewall
3. Look for errors in the browser console (F12)
4. The game will automatically fall back to AJAX polling

### Can't roll dice (button is grayed out)

Make sure:
1. You joined as a player (not spectator)
2. You are the current shooter (your name should show next to "Shooter:")
3. The game state is active

If you're the only player but still can't roll:
1. Open browser console (F12) and look for "Shooter check" logs
2. Run the debug script: `./debug-game.sh`
3. Reset the game: `sqlite3 database/craps_game.db "UPDATE players SET is_active = 0; UPDATE games SET shooter_id = NULL;"`
4. Refresh the page and join again - you'll be the shooter

### Players not updating

Check that:
1. JavaScript is enabled in your browser
2. Either WebSocket server is running OR API endpoints are accessible
3. Browser console for any errors

### "Game is full" message

The game supports 8 active players. If full:
1. Wait for inactive players to be cleaned up (60 seconds)
2. Or join as a spectator to watch the game
3. Players who leave (close browser) are automatically removed after 60 seconds

### Caddy won't start

If Caddy fails to start:
1. Check if port 8000 is already in use: `lsof -i :8000`
2. Verify PHP-FPM is running: `brew services list` (macOS) or `systemctl status php-fpm` (Linux)
3. Check Caddy logs: `tail -f /var/log/caddy/phpcraps.log`

## License

This project is open source and available for educational purposes.

## Contributing

Feel free to submit issues and enhancement requests!
