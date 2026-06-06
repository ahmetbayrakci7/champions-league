<?php

namespace App\Services\Contracts;

use Illuminate\Support\Collection;

interface FixtureGeneratorInterface
{
    /**
     * Build a double round-robin fixture for the given teams.
     *
     * @param  Collection<int, \App\Models\Team>  $teams
     * @return array<int, array{week: int, home_team_id: int, away_team_id: int}>
     */
    public function generate(Collection $teams): array;
}
