<?php

namespace App\Services\Contracts;

use App\Models\Team;

interface MatchSimulatorInterface
{
    /**
     * Simulate a single match and return the final score.
     *
     * @return array{home_goals: int, away_goals: int}
     */
    public function simulate(Team $home, Team $away, bool $neutral = false): array;

    /**
     * Expected goals for both sides. Neutral venues (the final) skip
     * home advantage and supporter boosts.
     *
     * @return array{0: float, 1: float}
     */
    public function expectedGoals(Team $home, Team $away, bool $neutral = false): array;

    /**
     * Analytic pre-match outcome odds from the double-Poisson model.
     *
     * @return array{home: float, draw: float, away: float} percentages, ~100 total
     */
    public function probabilities(Team $home, Team $away, bool $neutral = false): array;
}
