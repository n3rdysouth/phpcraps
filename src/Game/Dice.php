<?php

namespace Craps\Game;

/**
 * Dice class - handles rolling two dice
 */
class Dice
{
    private int $die1;
    private int $die2;
    private int $total;

    /**
     * Roll two dice
     * @return array ['die1' => int, 'die2' => int, 'total' => int]
     */
    public function roll(): array
    {
        $this->die1 = random_int(1, 6);
        $this->die2 = random_int(1, 6);
        $this->total = $this->die1 + $this->die2;

        return $this->getResult();
    }

    /**
     * Get the last roll result
     * @return array
     */
    public function getResult(): array
    {
        return [
            'die1' => $this->die1,
            'die2' => $this->die2,
            'total' => $this->total
        ];
    }

    /**
     * Get total of last roll
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }
}
