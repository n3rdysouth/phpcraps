<?php

namespace Craps\Database;

use PDO;
use PDOException;

/**
 * Database class - handles all database operations
 */
class Database
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        try {
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public function getPDO(): PDO
    {
        return $this->pdo;
    }

    // === GAME METHODS ===

    public function getGame(int $gameId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$gameId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function updateGame(int $gameId, string $phase, ?int $point, ?int $shooterId = null, bool $resetTurnTimer = false): bool
    {
        if ($resetTurnTimer) {
            $stmt = $this->pdo->prepare(
                "UPDATE games SET phase = ?, point = ?, shooter_id = ?, turn_started_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            );
        } else {
            $stmt = $this->pdo->prepare(
                "UPDATE games SET phase = ?, point = ?, shooter_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            );
        }
        return $stmt->execute([$phase, $point, $shooterId, $gameId]);
    }

    public function setGameStatus(int $gameId, string $status): bool
    {
        $stmt = $this->pdo->prepare("UPDATE games SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$status, $gameId]);
    }

    // === PLAYER METHODS ===

    public function addPlayer(int $gameId, string $name, float $bankroll, string $role): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO players (game_id, name, bankroll, role, last_active)
             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)"
        );
        $stmt->execute([$gameId, $name, $bankroll, $role]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getPlayer(int $playerId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM players WHERE id = ?");
        $stmt->execute([$playerId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getActivePlayers(int $gameId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM players WHERE game_id = ? AND is_active = 1 ORDER BY joined_at ASC"
        );
        $stmt->execute([$gameId]);
        return $stmt->fetchAll();
    }

    public function updatePlayerBankroll(int $playerId, float $bankroll): bool
    {
        $stmt = $this->pdo->prepare("UPDATE players SET bankroll = ? WHERE id = ?");
        return $stmt->execute([$bankroll, $playerId]);
    }

    public function setPlayerInactive(int $playerId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE players SET is_active = 0 WHERE id = ?");
        return $stmt->execute([$playerId]);
    }

    public function countActivePlayers(int $gameId, string $role = 'player'): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as count FROM players WHERE game_id = ? AND is_active = 1 AND role = ?"
        );
        $stmt->execute([$gameId, $role]);
        $result = $stmt->fetch();
        return (int)$result['count'];
    }

    public function updatePlayerActivity(int $playerId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE players SET last_active = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$playerId]);
    }

    public function cleanupInactivePlayers(int $gameId, int $timeoutSeconds = 60): array
    {
        // Get players who haven't been active for the timeout period
        $stmt = $this->pdo->prepare(
            "SELECT id, name FROM players
             WHERE game_id = ?
             AND is_active = 1
             AND datetime(last_active, '+' || ? || ' seconds') < CURRENT_TIMESTAMP"
        );
        $stmt->execute([$gameId, $timeoutSeconds]);
        $inactivePlayers = $stmt->fetchAll();

        // Mark them as inactive
        $cleanedIds = [];
        foreach ($inactivePlayers as $player) {
            $this->setPlayerInactive($player['id']);
            $cleanedIds[] = $player['id'];
        }

        return $cleanedIds;
    }

    // === BET METHODS ===

    public function placeBet(int $playerId, int $gameId, string $betType, float $amount): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO bets (player_id, game_id, bet_type, amount, status) VALUES (?, ?, ?, ?, 'active')"
        );
        $stmt->execute([$playerId, $gameId, $betType, $amount]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getActiveBets(int $gameId, ?string $betType = null): array
    {
        if ($betType) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM bets WHERE game_id = ? AND status = 'active' AND bet_type = ?"
            );
            $stmt->execute([$gameId, $betType]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM bets WHERE game_id = ? AND status = 'active'");
            $stmt->execute([$gameId]);
        }
        return $stmt->fetchAll();
    }

    public function getPlayerActiveBets(int $playerId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bets WHERE player_id = ? AND status = 'active'");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }

    public function resolveBet(int $betId, string $status, float $payout): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE bets SET status = ?, payout = ?, resolved_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        return $stmt->execute([$status, $payout, $betId]);
    }

    public function updateBetPointNumber(int $betId, int $pointNumber): bool
    {
        $stmt = $this->pdo->prepare("UPDATE bets SET point_number = ? WHERE id = ?");
        return $stmt->execute([$pointNumber, $betId]);
    }

    public function getComeBetsOnPoint(int $gameId, int $pointNumber): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM bets WHERE game_id = ? AND status = 'active' AND bet_type IN ('come', 'dont_come') AND point_number = ?"
        );
        $stmt->execute([$gameId, $pointNumber]);
        return $stmt->fetchAll();
    }

    // === ROLL METHODS ===

    public function recordRoll(int $gameId, int $playerId, int $die1, int $die2, string $phase, ?int $pointValue): int
    {
        $total = $die1 + $die2;
        $stmt = $this->pdo->prepare(
            "INSERT INTO rolls (game_id, player_id, die1, die2, total, phase, point_value)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$gameId, $playerId, $die1, $die2, $total, $phase, $pointValue]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getRecentRolls(int $gameId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, p.name as player_name
             FROM rolls r
             JOIN players p ON r.player_id = p.id
             WHERE r.game_id = ?
             ORDER BY r.rolled_at DESC
             LIMIT ?"
        );
        $stmt->execute([$gameId, $limit]);
        return $stmt->fetchAll();
    }
}
