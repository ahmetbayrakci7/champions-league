<?php

namespace App\Services;

use App\Services\Contracts\ChampionshipPredictorInterface;
use App\Services\Contracts\MatchSimulatorInterface;
use App\Services\Contracts\RandomGeneratorInterface;
use Illuminate\Support\Collection;

/**
 * Monte Carlo championship odds.
 *
 * The remaining fixtures are simulated N times; each run plays them out
 * and the share of runs a team tops the group becomes its championship
 * percentage. Mathematically decided situations converge to 100% / 0%
 * naturally (FAQ #8).
 *
 * Hot path, so it is hand-optimised: every remaining fixture's expected
 * goals are computed ONCE up front, each iteration only samples cheap
 * Poisson draws and tracks points/goals in flat arrays — no per-iteration
 * xG maths, DTOs or sorting.
 */
class ChampionshipPredictor implements ChampionshipPredictorInterface
{
    public function __construct(
        private readonly MatchSimulatorInterface $simulator,
        private readonly RandomGeneratorInterface $random,
    ) {
    }

    public function predict(Collection $teams, Collection $playedGames, Collection $remainingGames): array
    {
        $teamMap = $teams->keyBy('id');
        $ids = $teams->pluck('id')->all();

        // Base table from games already played (Premier League scoring).
        $points = array_fill_keys($ids, 0);
        $goalsFor = array_fill_keys($ids, 0);
        $goalsAgainst = array_fill_keys($ids, 0);

        foreach ($playedGames as $game) {
            $this->applyResult(
                $points, $goalsFor, $goalsAgainst,
                $game->home_team_id, $game->away_team_id, $game->home_goals, $game->away_goals,
            );
        }

        if ($remainingGames->isEmpty()) {
            return $this->finishedTable($ids, $points, $goalsFor, $goalsAgainst);
        }

        // Expected goals per remaining fixture — computed ONCE; only the
        // Poisson floor exp(-λ) is kept so iterations sample cheaply.
        $fixtures = [];

        foreach ($remainingGames as $game) {
            [$homeXg, $awayXg] = $this->simulator->expectedGoals(
                $teamMap[$game->home_team_id],
                $teamMap[$game->away_team_id],
            );

            $fixtures[] = [
                'home' => $game->home_team_id,
                'away' => $game->away_team_id,
                'homeLimit' => exp(-$homeXg),
                'awayLimit' => exp(-$awayXg),
            ];
        }

        $iterations = (int) config('league.prediction_iterations');
        $maxGoals = (int) config('league.max_goals_per_side');
        $champions = array_fill_keys($ids, 0);

        for ($i = 0; $i < $iterations; $i++) {
            $pts = $points;
            $gf = $goalsFor;
            $ga = $goalsAgainst;

            foreach ($fixtures as $fixture) {
                $homeGoals = $this->poisson($fixture['homeLimit'], $maxGoals);
                $awayGoals = $this->poisson($fixture['awayLimit'], $maxGoals);

                $this->applyResult($pts, $gf, $ga, $fixture['home'], $fixture['away'], $homeGoals, $awayGoals);
            }

            $champions[$this->leader($ids, $pts, $gf, $ga)]++;
        }

        return array_map(
            fn (int $count): float => round($count * 100 / $iterations, 1),
            $champions,
        );
    }

    /**
     * @param  array<int, int>  $points
     * @param  array<int, int>  $goalsFor
     * @param  array<int, int>  $goalsAgainst
     */
    private function applyResult(array &$points, array &$goalsFor, array &$goalsAgainst, int $home, int $away, int $homeGoals, int $awayGoals): void
    {
        $goalsFor[$home] += $homeGoals;
        $goalsAgainst[$home] += $awayGoals;
        $goalsFor[$away] += $awayGoals;
        $goalsAgainst[$away] += $homeGoals;

        if ($homeGoals > $awayGoals) {
            $points[$home] += 3;
        } elseif ($homeGoals < $awayGoals) {
            $points[$away] += 3;
        } else {
            $points[$home]++;
            $points[$away]++;
        }
    }

    /**
     * Group leader by Premier League ordering: points, goal difference,
     * goals scored (final name tiebreak is negligible for odds).
     *
     * @param  array<int, int>  $ids
     * @param  array<int, int>  $points
     * @param  array<int, int>  $goalsFor
     * @param  array<int, int>  $goalsAgainst
     */
    private function leader(array $ids, array $points, array $goalsFor, array $goalsAgainst): int
    {
        $best = $ids[0];
        $bestPts = $points[$best];
        $bestGd = $goalsFor[$best] - $goalsAgainst[$best];
        $bestGf = $goalsFor[$best];

        foreach ($ids as $id) {
            $pts = $points[$id];
            $gd = $goalsFor[$id] - $goalsAgainst[$id];
            $gf = $goalsFor[$id];

            if ($pts > $bestPts
                || ($pts === $bestPts && ($gd > $bestGd
                    || ($gd === $bestGd && $gf > $bestGf)))) {
                $best = $id;
                $bestPts = $pts;
                $bestGd = $gd;
                $bestGf = $gf;
            }
        }

        return $best;
    }

    /**
     * No fixtures left: the current leader is champion with certainty.
     *
     * @param  array<int, int>  $ids
     * @return array<int, float>
     */
    private function finishedTable(array $ids, array $points, array $goalsFor, array $goalsAgainst): array
    {
        $champion = $this->leader($ids, $points, $goalsFor, $goalsAgainst);

        return array_map(static fn (int $id): float => $id === $champion ? 100.0 : 0.0, array_combine($ids, $ids));
    }

    /**
     * Knuth Poisson sampler taking the precomputed floor exp(-λ).
     */
    private function poisson(float $limit, int $max): int
    {
        $product = 1.0;
        $count = -1;

        do {
            $count++;
            $product *= $this->random->float();
        } while ($product > $limit);

        return min($count, $max);
    }
}
