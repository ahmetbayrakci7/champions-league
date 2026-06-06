<?php

namespace App\Services\Contracts;

use Illuminate\Support\Collection;

interface ChampionshipPredictorInterface
{
    /**
     * Estimate each team's probability of winning the league by running
     * Monte Carlo simulations over the remaining fixtures.
     *
     * @param  Collection<int, \App\Models\Team>  $teams
     * @param  Collection<int, \App\Models\Game>  $playedGames
     * @param  Collection<int, \App\Models\Game>  $remainingGames
     * @return array<int, float> map of team id => percentage (0-100), sums to ~100
     */
    public function predict(Collection $teams, Collection $playedGames, Collection $remainingGames): array;
}
