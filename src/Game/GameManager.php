<?php

namespace Craps\Game;

use Craps\Database\Database;

/**
 * GameManager - Coordinates multi-player craps game
 */
class GameManager
{
    private Database $db;
    private int $gameId;
    private CrapsGame $game;
    private const MAX_PLAYERS = 8;

    public function __construct(Database $db, int $gameId = 1)
    {
        $this->db = $db;
        $this->gameId = $gameId;

        // Load game state from database
        $gameData = $this->db->getGame($gameId);
        if (!$gameData) {
            throw new \Exception("Game not found");
        }

        $this->game = new CrapsGame(
            $gameId,
            $gameData['phase'],
            $gameData['point']
        );
    }

    /**
     * Clean up inactive players (timeout after 60 seconds of no activity)
     */
    public function cleanupInactivePlayers(int $timeoutSeconds = 60): array
    {
        $cleanedIds = $this->db->cleanupInactivePlayers($this->gameId, $timeoutSeconds);

        // If shooter was cleaned up, rotate to next player
        if (!empty($cleanedIds)) {
            $gameData = $this->db->getGame($this->gameId);
            if (in_array($gameData['shooter_id'], $cleanedIds)) {
                $this->rotateShooter();
            }
        }

        return $cleanedIds;
    }

    /**
     * Update player activity timestamp
     */
    public function updatePlayerActivity(int $playerId): void
    {
        $this->db->updatePlayerActivity($playerId);
    }

    /**
     * Add a player to the game
     */
    public function joinGame(string $name, string $role = 'player'): array
    {
        // Clean up inactive players first to free up slots
        $this->cleanupInactivePlayers();

        // Check if game is full (for players)
        if ($role === 'player') {
            $playerCount = $this->db->countActivePlayers($this->gameId, 'player');
            if ($playerCount >= self::MAX_PLAYERS) {
                return ['success' => false, 'message' => 'Game is full. Joined as spectator.', 'role' => 'spectator'];
            }
        }

        // Add player to database
        $playerId = $this->db->addPlayer($this->gameId, $name, 1000.0, $role);

        // If there's no shooter or shooter is inactive, assign this player as shooter
        $gameData = $this->db->getGame($this->gameId);
        if (!$gameData['shooter_id']) {
            // No shooter at all - make this player the shooter and start timer
            $this->db->updateGame($this->gameId, $gameData['phase'], $gameData['point'], $playerId, true);
            $this->db->setGameStatus($this->gameId, 'active');
        } else {
            // Check if current shooter is still active
            $currentShooter = $this->db->getPlayer($gameData['shooter_id']);
            if (!$currentShooter || !$currentShooter['is_active']) {
                // Current shooter is gone, rotate to an active player
                $activePlayers = $this->db->getActivePlayers($this->gameId);
                if (count($activePlayers) > 0) {
                    // Make the first active player the shooter and start timer
                    $newShooterId = $activePlayers[0]['id'];
                    $this->db->updateGame($this->gameId, $gameData['phase'], $gameData['point'], $newShooterId, true);
                    $this->db->setGameStatus($this->gameId, 'active');
                }
            }
        }

        return [
            'success' => true,
            'player_id' => $playerId,
            'role' => $role,
            'message' => $role === 'player' ? 'Joined as player' : 'Joined as spectator'
        ];
    }

    /**
     * Place a bet for a player
     */
    public function placeBet(int $playerId, string $betType, float $amount): array
    {
        // Get player
        $playerData = $this->db->getPlayer($playerId);
        if (!$playerData || !$playerData['is_active']) {
            return ['success' => false, 'message' => 'Player not found or inactive'];
        }

        // Check if spectator
        if ($playerData['role'] === 'spectator') {
            return ['success' => false, 'message' => 'Spectators cannot place bets'];
        }

        // Get current game state
        $gameData = $this->db->getGame($this->gameId);

        // Check if betting time is closed (only for non-shooters)
        if ($gameData['shooter_id'] != $playerId) {
            $timerInfo = $this->getTimeRemaining();
            if ($timerInfo['betting_closed']) {
                return ['success' => false, 'message' => 'Betting time has closed. Wait for next roll.'];
            }
        }

        // Prevent place bets on the current point number
        if (in_array($betType, [Bet::PLACE_4, Bet::PLACE_5, Bet::PLACE_6, Bet::PLACE_8, Bet::PLACE_9, Bet::PLACE_10])) {
            if ($gameData['phase'] === 'point' && $gameData['point']) {
                $targetNumber = intval(substr($betType, strrpos($betType, '_') + 1));
                if ($targetNumber === (int)$gameData['point']) {
                    return ['success' => false, 'message' => 'Cannot place bet on the point number. Use Pass Line and Odds instead.'];
                }
            }
        }

        // Validate odds bets (Vegas rules)
        if ($betType === Bet::PASS_ODDS || $betType === Bet::DONT_PASS_ODDS) {
            // Rule 1: Point must be established
            if ($gameData['phase'] !== 'point' || !$gameData['point']) {
                return ['success' => false, 'message' => 'Odds bets can only be placed after a point is established'];
            }

            // Rule 2: Must have corresponding pass line or don't pass bet
            $baseBetType = $betType === Bet::PASS_ODDS ? Bet::PASS_LINE : Bet::DONT_PASS;
            $playerBets = $this->db->getPlayerActiveBets($playerId);
            $baseBet = null;

            foreach ($playerBets as $bet) {
                if ($bet['bet_type'] === $baseBetType) {
                    $baseBet = $bet;
                    break;
                }
            }

            if (!$baseBet) {
                $betName = $betType === Bet::PASS_ODDS ? 'Pass Line' : 'Don\'t Pass';
                return ['success' => false, 'message' => "You must have a {$betName} bet before placing odds"];
            }

            // Rule 3: Calculate max odds allowed (3x-4x-5x system)
            $point = $gameData['point'];
            $maxOddsMultiplier = $this->getMaxOddsMultiplier($point);
            $maxOddsAmount = $baseBet['amount'] * $maxOddsMultiplier;

            // Calculate current odds bet total
            $currentOddsTotal = 0;
            foreach ($playerBets as $bet) {
                if ($bet['bet_type'] === $betType) {
                    $currentOddsTotal += $bet['amount'];
                }
            }

            // Check if new bet would exceed max odds
            if (($currentOddsTotal + $amount) > $maxOddsAmount) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        'Odds bet exceeds maximum. Max: $%.2f (%.0fx your $%.2f bet), Current: $%.2f',
                        $maxOddsAmount,
                        $maxOddsMultiplier,
                        $baseBet['amount'],
                        $currentOddsTotal
                    )
                ];
            }
        }

        // Check bankroll
        if ($playerData['bankroll'] < $amount) {
            return ['success' => false, 'message' => 'Insufficient funds'];
        }

        // Deduct from bankroll
        $newBankroll = $playerData['bankroll'] - $amount;
        $this->db->updatePlayerBankroll($playerId, $newBankroll);

        // Place bet
        $betId = $this->db->placeBet($playerId, $this->gameId, $betType, $amount);

        return [
            'success' => true,
            'bet_id' => $betId,
            'new_bankroll' => $newBankroll,
            'message' => 'Bet placed successfully'
        ];
    }

    /**
     * Get maximum odds multiplier based on point (3x-4x-5x system)
     */
    private function getMaxOddsMultiplier(int $point): float
    {
        switch ($point) {
            case 4:
            case 10:
                return 3.0; // 3x odds on 4 and 10
            case 5:
            case 9:
                return 4.0; // 4x odds on 5 and 9
            case 6:
            case 8:
                return 5.0; // 5x odds on 6 and 8
            default:
                return 0.0;
        }
    }

    /**
     * Roll the dice (only shooter can roll)
     */
    public function rollDice(int $playerId): array
    {
        // Check if player is the shooter
        $gameData = $this->db->getGame($this->gameId);
        if ($gameData['shooter_id'] != $playerId) {
            return ['success' => false, 'message' => 'Only the shooter can roll'];
        }

        // Roll dice and get result
        $rollResult = $this->game->roll();

        // Record roll in database
        $this->db->recordRoll(
            $this->gameId,
            $playerId,
            $rollResult['roll']['die1'],
            $rollResult['roll']['die2'],
            $rollResult['phase'],
            $rollResult['point']
        );

        // If a point was just established, return any place bets on that number
        if ($rollResult['phase_changed'] && $rollResult['phase'] === 'point' && $rollResult['point']) {
            $this->returnPlaceBetsOnPoint($rollResult['point']);
        }

        // Update game state in database and reset turn timer
        $this->db->updateGame(
            $this->gameId,
            $this->game->getPhase(),
            $this->game->getPoint(),
            $gameData['shooter_id'],
            true  // Reset turn timer after roll
        );

        // Resolve bets based on roll result
        $this->resolveBets($rollResult);

        // If seven out, rotate shooter (which also resets timer)
        if ($rollResult['outcome'] === 'seven_out' && $rollResult['phase_changed']) {
            $this->rotateShooter();
        }

        return [
            'success' => true,
            'roll' => $rollResult
        ];
    }

    /**
     * Resolve all active bets based on roll result
     */
    private function resolveBets(array $rollResult): void
    {
        // Get all active bets
        $bets = $this->db->getActiveBets($this->gameId);

        foreach ($bets as $betData) {
            $bet = new Bet(
                $betData['id'],
                $betData['player_id'],
                $betData['game_id'],
                $betData['bet_type'],
                $betData['amount']
            );

            $shouldResolve = false;
            $status = Bet::STATUS_ACTIVE;
            $payout = 0;

            // Resolve based on bet type and result
            switch ($bet->getType()) {
                case Bet::PASS_LINE:
                    if ($rollResult['pass_line_result'] === 'win') {
                        $status = Bet::STATUS_WON;
                        $payout = Bet::calculatePayout(Bet::PASS_LINE, $bet->getAmount());
                        $shouldResolve = true;
                    } elseif ($rollResult['pass_line_result'] === 'lose') {
                        $status = Bet::STATUS_LOST;
                        $payout = 0;
                        $shouldResolve = true;
                    }
                    break;

                case Bet::DONT_PASS:
                    if ($rollResult['dont_pass_result'] === 'win') {
                        $status = Bet::STATUS_WON;
                        $payout = Bet::calculatePayout(Bet::DONT_PASS, $bet->getAmount());
                        $shouldResolve = true;
                    } elseif ($rollResult['dont_pass_result'] === 'lose') {
                        $status = Bet::STATUS_LOST;
                        $payout = 0;
                        $shouldResolve = true;
                    } elseif ($rollResult['dont_pass_result'] === 'push') {
                        $status = Bet::STATUS_PUSHED;
                        $payout = $bet->getAmount();
                        $shouldResolve = true;
                    }
                    break;

                case Bet::PASS_ODDS:
                    // Odds bets win when pass line wins (point is made)
                    if ($rollResult['pass_line_result'] === 'win' && $rollResult['point']) {
                        $status = Bet::STATUS_WON;
                        $payout = Bet::calculatePayout(Bet::PASS_ODDS, $bet->getAmount(), null, $rollResult['point']);
                        $shouldResolve = true;
                    } elseif ($rollResult['pass_line_result'] === 'lose') {
                        // Seven out - odds bet loses
                        $status = Bet::STATUS_LOST;
                        $payout = 0;
                        $shouldResolve = true;
                    }
                    break;

                case Bet::DONT_PASS_ODDS:
                    // Don't pass odds bet wins when a 7 is rolled (before point is made)
                    if ($rollResult['dont_pass_result'] === 'win' && $rollResult['roll']['total'] === 7) {
                        $status = Bet::STATUS_WON;
                        $payout = Bet::calculatePayout(Bet::DONT_PASS_ODDS, $bet->getAmount(), null, $rollResult['point']);
                        $shouldResolve = true;
                    } elseif ($rollResult['dont_pass_result'] === 'lose') {
                        // Point made - don't pass odds loses
                        $status = Bet::STATUS_LOST;
                        $payout = 0;
                        $shouldResolve = true;
                    }
                    break;

                case Bet::FIELD:
                    if ($rollResult['field_result'] === 'win') {
                        $status = Bet::STATUS_WON;
                        $payout = Bet::calculatePayout(Bet::FIELD, $bet->getAmount(), $rollResult['roll']['total']);
                        $shouldResolve = true;
                    } elseif ($rollResult['field_result'] === 'lose') {
                        $status = Bet::STATUS_LOST;
                        $payout = 0;
                        $shouldResolve = true;
                    }
                    break;

                case Bet::COME:
                    // Come bets: if no point_number set, behave like come out roll
                    // if point_number is set, win on that number, lose on 7
                    $comeBetPoint = $betData['point_number'];
                    $total = $rollResult['roll']['total'];

                    if ($comeBetPoint === null) {
                        // First roll after placing come bet
                        if ($total === 7 || $total === 11) {
                            // Natural - come bet wins immediately
                            $status = Bet::STATUS_WON;
                            $payout = Bet::calculatePayout(Bet::COME, $bet->getAmount());
                            $shouldResolve = true;
                        } elseif ($total === 2 || $total === 3 || $total === 12) {
                            // Craps - come bet loses immediately
                            $status = Bet::STATUS_LOST;
                            $payout = 0;
                            $shouldResolve = true;
                        } elseif (in_array($total, [4, 5, 6, 8, 9, 10])) {
                            // Point number rolled - move come bet to that number
                            $this->db->updateBetPointNumber($bet->getId(), $total);
                            // Bet stays active, now attached to this number
                        }
                    } else {
                        // Come bet has moved to a number
                        if ($total === $comeBetPoint) {
                            // Point hit - come bet wins
                            $status = Bet::STATUS_WON;
                            $payout = Bet::calculatePayout(Bet::COME, $bet->getAmount());
                            $shouldResolve = true;
                        } elseif ($total === 7) {
                            // Seven out - come bet loses
                            $status = Bet::STATUS_LOST;
                            $payout = 0;
                            $shouldResolve = true;
                        }
                    }
                    break;

                case Bet::DONT_COME:
                    // Don't come bets: opposite of come bets
                    $dontComeBetPoint = $betData['point_number'];
                    $total = $rollResult['roll']['total'];

                    if ($dontComeBetPoint === null) {
                        // First roll after placing don't come bet
                        if ($total === 7 || $total === 11) {
                            // Natural - don't come bet loses immediately
                            $status = Bet::STATUS_LOST;
                            $payout = 0;
                            $shouldResolve = true;
                        } elseif ($total === 2 || $total === 3) {
                            // Craps - don't come bet wins immediately
                            $status = Bet::STATUS_WON;
                            $payout = Bet::calculatePayout(Bet::DONT_COME, $bet->getAmount());
                            $shouldResolve = true;
                        } elseif ($total === 12) {
                            // 12 is a push for don't come
                            $status = Bet::STATUS_PUSHED;
                            $payout = $bet->getAmount();
                            $shouldResolve = true;
                        } elseif (in_array($total, [4, 5, 6, 8, 9, 10])) {
                            // Point number rolled - move don't come bet to that number
                            $this->db->updateBetPointNumber($bet->getId(), $total);
                            // Bet stays active, now attached to this number
                        }
                    } else {
                        // Don't come bet has moved to a number
                        if ($total === 7) {
                            // Seven rolled - don't come bet wins
                            $status = Bet::STATUS_WON;
                            $payout = Bet::calculatePayout(Bet::DONT_COME, $bet->getAmount());
                            $shouldResolve = true;
                        } elseif ($total === $dontComeBetPoint) {
                            // Point hit - don't come bet loses
                            $status = Bet::STATUS_LOST;
                            $payout = 0;
                            $shouldResolve = true;
                        }
                    }
                    break;

                case Bet::ANY_SEVEN:
                    // One-roll bet: wins if 7 is rolled
                    if ($rollResult['roll']['total'] === 7) {
                        $status = Bet::STATUS_WON;
                        $payout = Bet::calculatePayout(Bet::ANY_SEVEN, $bet->getAmount());
                        $shouldResolve = true;
                    } else {
                        $status = Bet::STATUS_LOST;
                        $payout = 0;
                        $shouldResolve = true;
                    }
                    break;

                case Bet::ANY_CRAPS:
                    // One-roll bet: wins if 2, 3, or 12 is rolled
                    $total = $rollResult['roll']['total'];
                    if ($total === 2 || $total === 3 || $total === 12) {
                        $status = Bet::STATUS_WON;
                        $payout = Bet::calculatePayout(Bet::ANY_CRAPS, $bet->getAmount());
                        $shouldResolve = true;
                    } else {
                        $status = Bet::STATUS_LOST;
                        $payout = 0;
                        $shouldResolve = true;
                    }
                    break;

                // Hardways bets
                case Bet::HARDWAY_4:
                case Bet::HARDWAY_6:
                case Bet::HARDWAY_8:
                case Bet::HARDWAY_10:
                    $total = $rollResult['roll']['total'];
                    $die1 = $rollResult['roll']['die1'];
                    $die2 = $rollResult['roll']['die2'];

                    // Extract target number from bet type (e.g., "hardway_10" -> 10)
                    $targetNumber = intval(substr($bet->getType(), strrpos($bet->getType(), '_') + 1));

                    if ($total === $targetNumber && $die1 === $die2) {
                        // Hard way wins
                        $status = Bet::STATUS_WON;
                        $payout = Bet::calculatePayout($bet->getType(), $bet->getAmount());
                        $shouldResolve = true;
                    } elseif ($total === $targetNumber && $die1 !== $die2) {
                        // Easy way loses
                        $status = Bet::STATUS_LOST;
                        $payout = 0;
                        $shouldResolve = true;
                    } elseif ($total === 7) {
                        // Seven out loses
                        $status = Bet::STATUS_LOST;
                        $payout = 0;
                        $shouldResolve = true;
                    }
                    // Otherwise stays active
                    break;

                // Place bets
                case Bet::PLACE_4:
                case Bet::PLACE_5:
                case Bet::PLACE_6:
                case Bet::PLACE_8:
                case Bet::PLACE_9:
                case Bet::PLACE_10:
                    $total = $rollResult['roll']['total'];

                    // Extract target number from bet type (e.g., "place_10" -> 10)
                    $targetNumber = intval(substr($bet->getType(), strrpos($bet->getType(), '_') + 1));

                    if ($total === $targetNumber) {
                        // Place bet WINS but STAYS on table (don't resolve)
                        $payout = Bet::calculatePayout($bet->getType(), $bet->getAmount());
                        // Don't set shouldResolve - bet stays active!

                        // Pay out immediately without resolving bet
                        $playerData = $this->db->getPlayer($bet->getPlayerId());
                        $newBankroll = $playerData['bankroll'] + $payout;
                        $this->db->updatePlayerBankroll($bet->getPlayerId(), $newBankroll);

                        error_log(sprintf(
                            "Place bet WON (stays active): Player %d, Bet %s, Amount: $%.2f, Payout: $%.2f",
                            $bet->getPlayerId(),
                            $bet->getType(),
                            $bet->getAmount(),
                            $payout
                        ));
                    } elseif ($total === 7) {
                        // Seven out - place bet loses and is removed
                        $status = Bet::STATUS_LOST;
                        $payout = 0;
                        $shouldResolve = true;
                    }
                    // Otherwise stays active
                    break;
            }

            // Update bet and player bankroll if resolved
            if ($shouldResolve) {
                $this->db->resolveBet($bet->getId(), $status, $payout);

                // Add payout to player bankroll (only if not already paid)
                if ($payout > 0) {
                    $playerData = $this->db->getPlayer($bet->getPlayerId());
                    $newBankroll = $playerData['bankroll'] + $payout;
                    $this->db->updatePlayerBankroll($bet->getPlayerId(), $newBankroll);

                    // Log payout for debugging
                    error_log(sprintf(
                        "Bet resolved: Player %d, Bet %s, Amount: $%.2f, Status: %s, Payout: $%.2f",
                        $bet->getPlayerId(),
                        $bet->getType(),
                        $bet->getAmount(),
                        $status,
                        $payout
                    ));
                }
            }
        }
    }

    /**
     * Return place bets when a number becomes the point
     * (Place bets are "off" when the number is the point)
     */
    private function returnPlaceBetsOnPoint(int $pointNumber): void
    {
        // Find place bet type for this number
        $placeBetType = 'place_' . $pointNumber;

        // Get all active place bets on this number
        $bets = $this->db->getActiveBets($this->gameId, $placeBetType);

        foreach ($bets as $betData) {
            // Return the bet amount to player's bankroll
            $playerData = $this->db->getPlayer($betData['player_id']);
            $newBankroll = $playerData['bankroll'] + $betData['amount'];
            $this->db->updatePlayerBankroll($betData['player_id'], $newBankroll);

            // Mark bet as returned (pushed)
            $this->db->resolveBet($betData['id'], Bet::STATUS_PUSHED, $betData['amount']);

            error_log(sprintf(
                "Place bet returned: Player %d had $%.2f on %s (point was established)",
                $betData['player_id'],
                $betData['amount'],
                $placeBetType
            ));
        }
    }

    /**
     * Rotate shooter to next active player
     */
    private function rotateShooter(): void
    {
        $players = $this->db->getActivePlayers($this->gameId);

        // If no active players, can't rotate shooter
        if (count($players) === 0) {
            return;
        }

        $gameData = $this->db->getGame($this->gameId);
        $currentShooterId = $gameData['shooter_id'];

        // Find current shooter index
        $currentIndex = 0;
        foreach ($players as $index => $player) {
            if ($player['id'] == $currentShooterId) {
                $currentIndex = $index;
                break;
            }
        }

        // Get next player (wrap around)
        $nextIndex = ($currentIndex + 1) % count($players);
        $nextShooterId = $players[$nextIndex]['id'];

        // Update game with new shooter and reset timer
        $this->db->updateGame($this->gameId, $gameData['phase'], $gameData['point'], $nextShooterId, true);
    }

    /**
     * Check if shooter's turn has timed out (20 seconds)
     * Returns true if timed out, false otherwise
     */
    public function checkShooterTimeout(): bool
    {
        $gameData = $this->db->getGame($this->gameId);

        // No timeout if no shooter or no turn_started_at
        if (!$gameData['shooter_id'] || !isset($gameData['turn_started_at']) || !$gameData['turn_started_at']) {
            return false;
        }

        // Calculate seconds elapsed
        $turnStarted = strtotime($gameData['turn_started_at']);
        $now = time();
        $elapsed = $now - $turnStarted;

        // 20 second timeout
        if ($elapsed >= 20) {
            // Auto-rotate shooter
            error_log("Shooter timeout: Player {$gameData['shooter_id']} took too long. Rotating shooter.");
            $this->rotateShooter();
            return true;
        }

        return false;
    }

    /**
     * Get time remaining for current shooter (in seconds)
     */
    public function getTimeRemaining(): array
    {
        $gameData = $this->db->getGame($this->gameId);

        if (!$gameData['shooter_id'] || !isset($gameData['turn_started_at']) || !$gameData['turn_started_at']) {
            return [
                'shooter_time_remaining' => 20,
                'betting_time_remaining' => 15,
                'betting_closed' => false
            ];
        }

        $turnStarted = strtotime($gameData['turn_started_at']);
        $now = time();
        $elapsed = $now - $turnStarted;

        $shooterTimeRemaining = max(0, 20 - $elapsed);
        $bettingTimeRemaining = max(0, 15 - $elapsed);

        return [
            'shooter_time_remaining' => $shooterTimeRemaining,
            'betting_time_remaining' => $bettingTimeRemaining,
            'betting_closed' => $elapsed >= 15
        ];
    }

    /**
     * Get current game state
     */
    public function getGameState(): array
    {
        // Check for shooter timeout before returning state
        $this->checkShooterTimeout();

        $gameData = $this->db->getGame($this->gameId);
        $players = $this->db->getActivePlayers($this->gameId);
        $recentRolls = $this->db->getRecentRolls($this->gameId, 5);

        // Get active bets summary
        $activeBets = $this->db->getActiveBets($this->gameId);

        // Get timer info
        $timerInfo = $this->getTimeRemaining();

        return [
            'game' => $gameData,
            'players' => $players,
            'recent_rolls' => $recentRolls,
            'active_bets_count' => count($activeBets),
            'shooter_id' => $gameData['shooter_id'],
            'timer' => $timerInfo
        ];
    }

    /**
     * Get player info with their bets
     */
    public function getPlayerInfo(int $playerId): array
    {
        $player = $this->db->getPlayer($playerId);
        $activeBets = $this->db->getPlayerActiveBets($playerId);

        return [
            'player' => $player,
            'active_bets' => $activeBets
        ];
    }
}
