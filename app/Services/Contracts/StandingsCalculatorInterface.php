<?php

namespace App\Services\Contracts;

use App\DataTransferObjects\TeamStanding;
use Illuminate\Support\Collection;

interface StandingsCalculatorInterface
{
    /**
     * Compute the league table from played results, ordered by
     * Premier League rules: points, goal difference, goals scored.
     *
     * @param  Collection<int, \App\Models\Team>  $teams
     * @param  iterable<array{home_team_id: int, away_team_id: int, home_goals: int, away_goals: int}>  $results
     * @return array<int, TeamStanding> ranked standings, champion first
     */
    public function calculate(Collection $teams, iterable $results): array;
}
