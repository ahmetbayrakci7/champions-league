<?php

namespace App\Services;

use App\DataTransferObjects\TeamStanding;
use App\Services\Contracts\StandingsCalculatorInterface;
use Illuminate\Support\Collection;

class StandingsCalculator implements StandingsCalculatorInterface
{
    public function calculate(Collection $teams, iterable $results): array
    {
        /** @var array<int, TeamStanding> $standings */
        $standings = [];

        foreach ($teams as $team) {
            $standings[$team->id] = new TeamStanding($team->id, $team->name, $team->code ?? '', $team->color ?? '', $team->logo_url ?? null);
        }

        foreach ($results as $result) {
            $result = is_array($result) ? $result : $result->toArray();

            $standings[$result['home_team_id']]->recordResult($result['home_goals'], $result['away_goals']);
            $standings[$result['away_team_id']]->recordResult($result['away_goals'], $result['home_goals']);
        }

        // Premier League ordering: points, goal difference, goals scored, then name.
        usort($standings, fn (TeamStanding $a, TeamStanding $b): int => [$b->points(), $b->goalDifference(), $b->goalsFor, $a->teamName]
            <=> [$a->points(), $a->goalDifference(), $a->goalsFor, $b->teamName]);

        return array_values($standings);
    }
}
