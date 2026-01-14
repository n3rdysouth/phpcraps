<?php

namespace Craps\Game;

/**
 * Bet class - represents a bet placed by a player
 */
class Bet
{
    // Bet type constants
    public const PASS_LINE = 'pass_line';
    public const DONT_PASS = 'dont_pass';
    public const PASS_ODDS = 'pass_odds';
    public const DONT_PASS_ODDS = 'dont_pass_odds';
    public const COME = 'come';
    public const DONT_COME = 'dont_come';
    public const FIELD = 'field';
    public const ANY_CRAPS = 'any_craps';
    public const ANY_SEVEN = 'any_seven';

    // Hardways bets
    public const HARDWAY_4 = 'hardway_4';
    public const HARDWAY_6 = 'hardway_6';
    public const HARDWAY_8 = 'hardway_8';
    public const HARDWAY_10 = 'hardway_10';

    // Place bets
    public const PLACE_4 = 'place_4';
    public const PLACE_5 = 'place_5';
    public const PLACE_6 = 'place_6';
    public const PLACE_8 = 'place_8';
    public const PLACE_9 = 'place_9';
    public const PLACE_10 = 'place_10';

    // Bet status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_WON = 'won';
    public const STATUS_LOST = 'lost';
    public const STATUS_PUSHED = 'pushed';

    private int $id;
    private int $playerId;
    private int $gameId;
    private string $type;
    private float $amount;
    private string $status;
    private ?float $payout;

    public function __construct(
        int $id,
        int $playerId,
        int $gameId,
        string $type,
        float $amount,
        string $status = self::STATUS_ACTIVE,
        ?float $payout = null
    ) {
        $this->id = $id;
        $this->playerId = $playerId;
        $this->gameId = $gameId;
        $this->type = $type;
        $this->amount = $amount;
        $this->status = $status;
        $this->payout = $payout;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPlayerId(): int
    {
        return $this->playerId;
    }

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPayout(): ?float
    {
        return $this->payout;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Mark bet as won with payout
     */
    public function win(float $payout): void
    {
        $this->status = self::STATUS_WON;
        $this->payout = $payout;
    }

    /**
     * Mark bet as lost
     */
    public function lose(): void
    {
        $this->status = self::STATUS_LOST;
        $this->payout = 0;
    }

    /**
     * Mark bet as pushed (tie)
     */
    public function push(): void
    {
        $this->status = self::STATUS_PUSHED;
        $this->payout = $this->amount; // Return original bet
    }

    /**
     * Calculate payout based on bet type and odds
     */
    public static function calculatePayout(string $betType, float $amount, int $total = null, int $point = null): float
    {
        switch ($betType) {
            case self::PASS_LINE:
            case self::DONT_PASS:
            case self::COME:
            case self::DONT_COME:
                return $amount * 2; // 1:1 odds (bet + winnings)

            case self::PASS_ODDS:
            case self::DONT_PASS_ODDS:
                // Odds bets pay true odds based on the point
                if ($point === null) {
                    return $amount; // No payout if no point
                }
                // True odds: 4/10 pays 2:1, 5/9 pays 3:2, 6/8 pays 6:5
                switch ($point) {
                    case 4:
                    case 10:
                        return $amount + ($amount * 2); // 2:1
                    case 5:
                    case 9:
                        return $amount + ($amount * 1.5); // 3:2
                    case 6:
                    case 8:
                        return $amount + ($amount * 1.2); // 6:5
                    default:
                        return $amount;
                }

            case self::FIELD:
                // Field bet: 2 and 12 pay 2:1, 3,4,9,10,11 pay 1:1
                if ($total === 2 || $total === 12) {
                    return $amount * 3; // 2:1 (bet + 2x winnings)
                }
                return $amount * 2; // 1:1

            case self::ANY_CRAPS:
                return $amount * 8; // 7:1 odds

            case self::ANY_SEVEN:
                return $amount * 5; // 4:1 odds

            // Hardways bets
            case self::HARDWAY_4:
            case self::HARDWAY_10:
                return $amount * 8; // 7:1 odds

            case self::HARDWAY_6:
            case self::HARDWAY_8:
                return $amount * 10; // 9:1 odds

            // Place bets
            case self::PLACE_4:
            case self::PLACE_10:
                return $amount + ($amount * 1.8); // 9:5 odds

            case self::PLACE_5:
            case self::PLACE_9:
                return $amount + ($amount * 1.4); // 7:5 odds

            case self::PLACE_6:
            case self::PLACE_8:
                return $amount + ($amount * 1.167); // 7:6 odds

            default:
                return $amount * 2; // Default 1:1
        }
    }

    /**
     * Convert bet to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'player_id' => $this->playerId,
            'game_id' => $this->gameId,
            'type' => $this->type,
            'amount' => $this->amount,
            'status' => $this->status,
            'payout' => $this->payout
        ];
    }
}
