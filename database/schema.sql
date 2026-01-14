-- Craps Game Database Schema (SQLite)

-- Games table: stores active games
CREATE TABLE IF NOT EXISTS games (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    status TEXT NOT NULL DEFAULT 'waiting', -- waiting, active, finished
    phase TEXT NOT NULL DEFAULT 'come_out', -- come_out, point
    point INTEGER DEFAULT NULL, -- the point number (4,5,6,8,9,10)
    shooter_id INTEGER DEFAULT NULL, -- current shooter player ID
    turn_started_at DATETIME DEFAULT NULL, -- when current shooter's turn started (for timeout)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Players table: stores player info for each game
CREATE TABLE IF NOT EXISTS players (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    bankroll REAL NOT NULL DEFAULT 1000.0, -- starting chips
    role TEXT NOT NULL DEFAULT 'player', -- player or spectator
    is_active INTEGER NOT NULL DEFAULT 1, -- 1 = active, 0 = left
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_active DATETIME DEFAULT CURRENT_TIMESTAMP, -- for tracking inactive players
    FOREIGN KEY (game_id) REFERENCES games(id)
);

-- Bets table: stores all bets placed
CREATE TABLE IF NOT EXISTS bets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    player_id INTEGER NOT NULL,
    game_id INTEGER NOT NULL,
    bet_type TEXT NOT NULL, -- pass_line, dont_pass, come, field, etc
    amount REAL NOT NULL,
    status TEXT NOT NULL DEFAULT 'active', -- active, won, lost, pushed
    point_number INTEGER DEFAULT NULL, -- for come bets: which number the bet moved to
    placed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    payout REAL DEFAULT 0,
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (game_id) REFERENCES games(id)
);

-- Rolls table: history of all dice rolls
CREATE TABLE IF NOT EXISTS rolls (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id INTEGER NOT NULL,
    player_id INTEGER NOT NULL, -- who rolled
    die1 INTEGER NOT NULL,
    die2 INTEGER NOT NULL,
    total INTEGER NOT NULL,
    phase TEXT NOT NULL, -- come_out or point
    point_value INTEGER DEFAULT NULL, -- point at time of roll
    rolled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id),
    FOREIGN KEY (player_id) REFERENCES players(id)
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_players_game ON players(game_id);
CREATE INDEX IF NOT EXISTS idx_bets_player ON bets(player_id);
CREATE INDEX IF NOT EXISTS idx_bets_game ON bets(game_id);
CREATE INDEX IF NOT EXISTS idx_rolls_game ON rolls(game_id);
