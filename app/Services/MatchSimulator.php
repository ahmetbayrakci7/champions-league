<?php

namespace App\Services;

use App\Models\Team;
use App\Services\Contracts\MatchSimulatorInterface;
use App\Services\Contracts\RandomGeneratorInterface;
use App\Services\Support\SamplesPoisson;

/**
 * Poisson-based score simulation.
 *
 * Each side gets an expected-goal (xG) value derived from its power,
 * home advantage, supporter strength and the opponent's goalkeeper.
 * Actual goals are drawn from a Poisson distribution, so the stronger
 * side wins most of the time while upsets stay possible (FAQ #5/#6).
 */
class MatchSimulator implements MatchSimulatorInterface
{
    use SamplesPoisson;

    public function __construct(
        private readonly RandomGeneratorInterface $random,
    ) {
    }

    public function simulate(Team $home, Team $away, bool $neutral = false): array
    {
        [$homeXg, $awayXg] = $this->expectedGoals($home, $away, $neutral);

        return [
            'home_goals' => $this->poisson($homeXg, $this->random),
            'away_goals' => $this->poisson($awayXg, $this->random),
        ];
    }

    public function expectedGoals(Team $home, Team $away, bool $neutral = false): array
    {
        $homeEffective = (float) $home->power;

        if (! $neutral) {
            $homeEffective += $home->home_advantage * (float) config('league.home_advantage_power')
                + $home->supporter_strength * (float) config('league.supporter_power');
        }

        $awayEffective = (float) $away->power;

        // Logistic share over a CONVEX gap curve: 1-2 point gaps stay
        // genuinely competitive, 4+ point gaps become dominant.
        $delta = $homeEffective - $awayEffective;
        $gamma = (float) config('league.strength_gamma');
        $scale = (float) config('league.strength_scale');

        $x = $delta === 0.0 ? 0.0 : (abs($delta) ** $gamma) / $scale * ($delta <=> 0);
        $share = 1 / (1 + exp(-$x));

        $totalGoals = (float) config('league.average_total_goals');

        // Opposing goalkeeper suppresses a side's expected goals.
        $homeXg = $totalGoals * $share * (1 - $away->goalkeeper_factor / config('league.goalkeeper_dampening'));
        $awayXg = $totalGoals * (1 - $share) * (1 - $home->goalkeeper_factor / config('league.goalkeeper_dampening'));

        $floor = (float) config('league.min_expected_goals');

        return [max($homeXg, $floor), max($awayXg, $floor)];
    }

    public function probabilities(Team $home, Team $away, bool $neutral = false): array
    {
        [$homeXg, $awayXg] = $this->expectedGoals($home, $away, $neutral);

        $max = (int) config('league.max_goals_per_side');
        $homePmf = $this->poissonPmf($homeXg, $max);
        $awayPmf = $this->poissonPmf($awayXg, $max);

        $homeWin = $draw = $awayWin = 0.0;

        foreach ($homePmf as $h => $ph) {
            foreach ($awayPmf as $a => $pa) {
                $product = $ph * $pa;

                match (true) {
                    $h > $a => $homeWin += $product,
                    $h < $a => $awayWin += $product,
                    default => $draw += $product,
                };
            }
        }

        $total = $homeWin + $draw + $awayWin;

        return [
            'home' => round($homeWin / $total * 100, 1),
            'draw' => round($draw / $total * 100, 1),
            'away' => round($awayWin / $total * 100, 1),
        ];
    }

    /**
     * @return array<int, float> P(goals = k) for k in [0, max]
     */
    private function poissonPmf(float $lambda, int $max): array
    {
        $pmf = [];
        $term = exp(-$lambda); // k = 0

        for ($k = 0; $k <= $max; $k++) {
            $pmf[$k] = $term;
            $term *= $lambda / ($k + 1);
        }

        return $pmf;
    }
}
