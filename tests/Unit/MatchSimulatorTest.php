<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Services\MatchSimulator;
use App\Services\Support\MtRandomGenerator;
use Tests\Support\SequenceRandomGenerator;
use Tests\TestCase;

class MatchSimulatorTest extends TestCase
{
    private function team(int $power, int $homeAdvantage = 10, int $supporters = 80, int $goalkeeper = 80): Team
    {
        return new Team([
            'name' => "Power {$power}",
            'code' => 'TST',
            'power' => $power,
            'home_advantage' => $homeAdvantage,
            'supporter_strength' => $supporters,
            'goalkeeper_factor' => $goalkeeper,
        ]);
    }

    public function test_stronger_home_side_gets_higher_expected_goals(): void
    {
        $simulator = new MatchSimulator(new SequenceRandomGenerator([0.5]));

        [$homeXg, $awayXg] = $simulator->expectedGoals($this->team(90), $this->team(50));

        $this->assertGreaterThan($awayXg, $homeXg);
    }

    public function test_expected_goals_never_drop_below_the_floor(): void
    {
        $simulator = new MatchSimulator(new SequenceRandomGenerator([0.5]));

        [, $awayXg] = $simulator->expectedGoals(
            $this->team(100, 20, 100, 100),
            $this->team(1, 0, 0, 0),
        );

        $this->assertGreaterThanOrEqual(config('league.min_expected_goals'), $awayXg);
    }

    public function test_simulation_returns_non_negative_integer_scores(): void
    {
        $simulator = new MatchSimulator(new MtRandomGenerator());

        $score = $simulator->simulate($this->team(85), $this->team(70));

        $this->assertGreaterThanOrEqual(0, $score['home_goals']);
        $this->assertGreaterThanOrEqual(0, $score['away_goals']);
        $this->assertLessThanOrEqual(config('league.max_goals_per_side'), $score['home_goals']);
        $this->assertLessThanOrEqual(config('league.max_goals_per_side'), $score['away_goals']);
    }

    public function test_much_stronger_team_wins_clearly_more_often(): void
    {
        $simulator = new MatchSimulator(new MtRandomGenerator());
        $strong = $this->team(95);
        $weak = $this->team(20);

        $strongWins = 0;
        $weakWins = 0;

        for ($i = 0; $i < 600; $i++) {
            $score = $simulator->simulate($strong, $weak);

            if ($score['home_goals'] > $score['away_goals']) {
                $strongWins++;
            } elseif ($score['away_goals'] > $score['home_goals']) {
                $weakWins++;
            }
        }

        $this->assertGreaterThan($weakWins * 2, $strongWins, 'Strong team should dominate the weak one');
    }

    public function test_probabilities_sum_to_one_hundred_and_favour_the_stronger_side(): void
    {
        $simulator = new MatchSimulator(new SequenceRandomGenerator([0.5]));

        $odds = $simulator->probabilities($this->team(90), $this->team(60));

        $this->assertEqualsWithDelta(100.0, $odds['home'] + $odds['draw'] + $odds['away'], 0.2);
        $this->assertGreaterThan($odds['away'], $odds['home']);
    }

    public function test_neutral_venue_removes_the_home_boost(): void
    {
        $simulator = new MatchSimulator(new SequenceRandomGenerator([0.5]));

        $withCrowd = $simulator->probabilities($this->team(80), $this->team(80));
        $neutral = $simulator->probabilities($this->team(80), $this->team(80), neutral: true);

        $this->assertGreaterThan($neutral['home'], $withCrowd['home']);
        $this->assertEqualsWithDelta($neutral['home'], $neutral['away'], 0.2, 'Equal teams on neutral ground should be even');
    }

    public function test_weak_team_still_has_a_small_chance(): void
    {
        $simulator = new MatchSimulator(new MtRandomGenerator());
        $strong = $this->team(95);
        $weak = $this->team(20);

        $weakWins = 0;

        for ($i = 0; $i < 2000; $i++) {
            $score = $simulator->simulate($strong, $weak);

            if ($score['away_goals'] > $score['home_goals']) {
                $weakWins++;
            }
        }

        $this->assertGreaterThan(0, $weakWins, 'Upsets must remain possible (FAQ #5)');
    }
}
