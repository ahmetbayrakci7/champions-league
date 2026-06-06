<?php

namespace App\DataTransferObjects;

/**
 * Accumulator for a single team's row in the league table.
 */
final class TeamStanding
{
    public int $played = 0;

    public int $won = 0;

    public int $drawn = 0;

    public int $lost = 0;

    public int $goalsFor = 0;

    public int $goalsAgainst = 0;

    public function __construct(
        public readonly int $teamId,
        public readonly string $teamName,
        public readonly string $teamCode = '',
        public readonly string $teamColor = '',
        public readonly ?string $teamLogo = null,
    ) {
    }

    public function recordResult(int $scored, int $conceded): void
    {
        $this->played++;
        $this->goalsFor += $scored;
        $this->goalsAgainst += $conceded;

        match (true) {
            $scored > $conceded => $this->won++,
            $scored < $conceded => $this->lost++,
            default => $this->drawn++,
        };
    }

    public function goalDifference(): int
    {
        return $this->goalsFor - $this->goalsAgainst;
    }

    public function points(): int
    {
        return $this->won * 3 + $this->drawn;
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'team_id' => $this->teamId,
            'name' => $this->teamName,
            'code' => $this->teamCode,
            'color' => $this->teamColor,
            'logo_url' => $this->teamLogo,
            'played' => $this->played,
            'won' => $this->won,
            'drawn' => $this->drawn,
            'lost' => $this->lost,
            'goals_for' => $this->goalsFor,
            'goals_against' => $this->goalsAgainst,
            'goal_difference' => $this->goalDifference(),
            'points' => $this->points(),
        ];
    }
}
