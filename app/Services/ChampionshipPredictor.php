<?php

namespace App\Services;

use App\Services\Contracts\ChampionshipPredictorInterface;
use App\Services\Contracts\MatchSimulatorInterface;
use App\Services\Contracts\StandingsCalculatorInterface;
use Illuminate\Support\Collection;

/**
 * Monte Carlo championship odds.
 *
 * The remaining fixtures are simulated N times with the same engine that
 * plays real matches; each run produces a final table and the share of
 * runs a team tops becomes its championship percentage. Mathematically
 * decided situations therefore converge to 100% / 0% naturally (FAQ #8).
 */
class ChampionshipPredictor implements ChampionshipPredictorInterface
{
    public function __construct(
        private readonly MatchSimulatorInterface $simulator,
        private readonly StandingsCalculatorInterface $standings,
    ) {
    }

    public function predict(Collection $teams, Collection $playedGames, Collection $remainingGames): array
    {
        $teamMap = $teams->keyBy('id');
        $champions = array_fill_keys($teams->pluck('id')->all(), 0);

        $playedResults = $playedGames->map(fn ($game) => [
            'home_team_id' => $game->home_team_id,
            'away_team_id' => $game->away_team_id,
            'home_goals' => $game->home_goals,
            'away_goals' => $game->away_goals,
        ])->all();

        $iterations = $remainingGames->isEmpty() ? 1 : (int) config('league.prediction_iterations');

        for ($i = 0; $i < $iterations; $i++) {
            $results = $playedResults;

            foreach ($remainingGames as $game) {
                $score = $this->simulator->simulate(
                    $teamMap[$game->home_team_id],
                    $teamMap[$game->away_team_id],
                );

                $results[] = [
                    'home_team_id' => $game->home_team_id,
                    'away_team_id' => $game->away_team_id,
                    'home_goals' => $score['home_goals'],
                    'away_goals' => $score['away_goals'],
                ];
            }

            $table = $this->standings->calculate($teams, $results);
            $champions[$table[0]->teamId]++;
        }

        return array_map(
            fn (int $count): float => round($count * 100 / $iterations, 1),
            $champions,
        );
    }
}
