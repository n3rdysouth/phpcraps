<?php

namespace Craps\Game;

/**
 * CrapsGame class - handles craps game logic and rules
 */
class CrapsGame
{
    // Game phases
    public const PHASE_COME_OUT = 'come_out';
    public const PHASE_POINT = 'point';

    private int $id;
    private string $phase;
    private ?int $point;
    private Dice $dice;
    private array $lastRoll;
    private array $eventLog;

    public function __construct(int $id, string $phase = self::PHASE_COME_OUT, ?int $point = null)
    {
        $this->id = $id;
        $this->phase = $phase;
        $this->point = $point;
        $this->dice = new Dice();
        $this->lastRoll = [];
        $this->eventLog = [];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPhase(): string
    {
        return $this->phase;
    }

    public function getPoint(): ?int
    {
        return $this->point;
    }

    public function getLastRoll(): array
    {
        return $this->lastRoll;
    }

    public function getEventLog(): array
    {
        return $this->eventLog;
    }

    /**
     * Roll the dice and process game logic
     * @return array Roll result with game state changes
     */
    public function roll(): array
    {
        // Roll the dice
        $this->lastRoll = $this->dice->roll();
        $total = $this->lastRoll['total'];

        $result = [
            'roll' => $this->lastRoll,
            'phase' => $this->phase,
            'point' => $this->point,
            'outcome' => null,
            'pass_line_result' => null,
            'dont_pass_result' => null,
            'field_result' => null,
            'phase_changed' => false,
            'message' => ''
        ];

        // Process based on current phase
        if ($this->phase === self::PHASE_COME_OUT) {
            $result = $this->processComeOutRoll($total, $result);
        } else {
            $result = $this->processPointRoll($total, $result);
        }

        // Add to event log
        $this->addEvent($result['message']);

        return $result;
    }

    /**
     * Process Come Out Roll
     */
    private function processComeOutRoll(int $total, array $result): array
    {
        $result['message'] = "Come Out Roll: $total";

        // Natural: 7 or 11 wins
        if ($total === 7 || $total === 11) {
            $result['outcome'] = 'natural_win';
            $result['pass_line_result'] = 'win';
            $result['dont_pass_result'] = 'lose';
            $result['message'] .= " - Natural! Pass Line wins!";
        }
        // Craps: 2, 3, or 12 loses
        elseif ($total === 2 || $total === 3 || $total === 12) {
            $result['outcome'] = 'craps';
            $result['pass_line_result'] = 'lose';

            // 12 is a push for Don't Pass
            if ($total === 12) {
                $result['dont_pass_result'] = 'push';
                $result['message'] .= " - Craps! Pass Line loses, Don't Pass pushes.";
            } else {
                $result['dont_pass_result'] = 'win';
                $result['message'] .= " - Craps! Pass Line loses, Don't Pass wins!";
            }
        }
        // Point established: 4, 5, 6, 8, 9, 10
        else {
            $this->point = $total;
            $this->phase = self::PHASE_POINT;
            $result['outcome'] = 'point_established';
            $result['point'] = $this->point;
            $result['phase'] = $this->phase;
            $result['phase_changed'] = true;
            $result['message'] .= " - Point is $total";
        }

        // Process Field bet (2,3,4,9,10,11,12 wins, others lose)
        $result['field_result'] = $this->processFieldBet($total);

        return $result;
    }

    /**
     * Process Point Roll
     */
    private function processPointRoll(int $total, array $result): array
    {
        $result['message'] = "Point Roll: $total (Point is {$this->point})";

        // Made the point - Pass Line wins
        if ($total === $this->point) {
            $result['outcome'] = 'point_made';
            $result['pass_line_result'] = 'win';
            $result['dont_pass_result'] = 'lose';
            $result['message'] .= " - Point made! Pass Line wins!";

            // Reset to Come Out phase
            $this->phase = self::PHASE_COME_OUT;
            $this->point = null;
            $result['phase'] = $this->phase;
            $result['point'] = $this->point;
            $result['phase_changed'] = true;
        }
        // Seven out - Don't Pass wins
        elseif ($total === 7) {
            $result['outcome'] = 'seven_out';
            $result['pass_line_result'] = 'lose';
            $result['dont_pass_result'] = 'win';
            $result['message'] .= " - Seven out! Don't Pass wins!";

            // Reset to Come Out phase
            $this->phase = self::PHASE_COME_OUT;
            $this->point = null;
            $result['phase'] = $this->phase;
            $result['point'] = $this->point;
            $result['phase_changed'] = true;
        }
        // No decision - keep rolling
        else {
            $result['outcome'] = 'no_decision';
            $result['message'] .= " - No decision, keep rolling.";
        }

        // Process Field bet
        $result['field_result'] = $this->processFieldBet($total);

        return $result;
    }

    /**
     * Process Field Bet result
     * Field wins on 2,3,4,9,10,11,12
     */
    private function processFieldBet(int $total): string
    {
        $fieldNumbers = [2, 3, 4, 9, 10, 11, 12];
        return in_array($total, $fieldNumbers) ? 'win' : 'lose';
    }

    /**
     * Add event to log
     */
    private function addEvent(string $message): void
    {
        $this->eventLog[] = [
            'time' => date('H:i:s'),
            'message' => $message
        ];
    }

    /**
     * Get game state as array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'phase' => $this->phase,
            'point' => $this->point,
            'last_roll' => $this->lastRoll
        ];
    }
}
