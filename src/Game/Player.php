<?php

namespace Craps\Game;

/**
 * Player class - represents a player in the game
 */
class Player
{
    private int $id;
    private int $gameId;
    private string $name;
    private float $bankroll;
    private string $role; // 'player' or 'spectator'
    private bool $isActive;

    public function __construct(
        int $id,
        int $gameId,
        string $name,
        float $bankroll = 1000.0,
        string $role = 'player',
        bool $isActive = true
    ) {
        $this->id = $id;
        $this->gameId = $gameId;
        $this->name = $name;
        $this->bankroll = $bankroll;
        $this->role = $role;
        $this->isActive = $isActive;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBankroll(): float
    {
        return $this->bankroll;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isPlayer(): bool
    {
        return $this->role === 'player';
    }

    public function isSpectator(): bool
    {
        return $this->role === 'spectator';
    }

    /**
     * Add chips to bankroll
     */
    public function addChips(float $amount): void
    {
        $this->bankroll += $amount;
    }

    /**
     * Deduct chips from bankroll
     */
    public function deductChips(float $amount): bool
    {
        if ($this->bankroll >= $amount) {
            $this->bankroll -= $amount;
            return true;
        }
        return false;
    }

    /**
     * Check if player can afford a bet
     */
    public function canAfford(float $amount): bool
    {
        return $this->bankroll >= $amount;
    }

    /**
     * Set player as inactive (left game)
     */
    public function leave(): void
    {
        $this->isActive = false;
    }

    /**
     * Convert player to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'game_id' => $this->gameId,
            'name' => $this->name,
            'bankroll' => $this->bankroll,
            'role' => $this->role,
            'is_active' => $this->isActive
        ];
    }
}
