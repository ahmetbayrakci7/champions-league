<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\Team;
use App\Services\ChampionshipPredictor;
use App\Services\Contracts\MatchSimulatorInterface;
use App\Services\MatchSimulator;
use App\Services\StandingsCalculator;
use App\Services\Support\MtRandomGenerator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ChampionshipPredictorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['league.prediction_iterations' => 300]);
    }

    /**
     * @return Collection<int, Team>
     */
    private function teams(): Collection
    {
        $specs = [
            ['id' => 1, 'name' => 'Alpha', 'power' => 90],
            ['id' => 2, 'name' => 'Bravo', 'power' => 80],
            ['id' => 3, 'name' => 'Charlie', 'power' => 70],
            ['id' => 4, 'name' => 'Delta', 'power' => 60],
        ];

        return collect($specs)->map(function (array $spec): Team {
            $team = new Team([
                'name' => $spec['name'],
                'code' => strtoupper(substr($spec['name'], 0, 3)),
                'power' => $spec['power'],
                'home_advantage' => 10,
                'supporter_strength' => 80,
                'goalkeeper_factor' => 80,
            ]);
            $team->id = $spec['id'];

            return $team;
        });
    }

    private function game(int $home, int $away, ?int $homeGoals = null, ?int $awayGoals = null): Game
    {
        return new Game([
            'week' => 1,
            'home_team_id' => $home,
            'away_team_id' => $away,
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'is_played' => $homeGoals !== null,
        ]);
    }

    private function predictor(?MatchSimulatorInterface $simulator = null): ChampionshipPredictor
    {
        return new ChampionshipPredictor(
            $simulator ?? new MatchSimulator(new MtRandomGenerator()),
            new StandingsCalculator(),
        );
    }

    public function test_percentages_sum_to_one_hundred(): void
    {
        $teams = $this->teams();

        $played = collect([
            $this->game(1, 2, 2, 1),
            $this->game(3, 4, 1, 1),
        ]);

        $remaining = collect([
            $this->game(1, 3),
            $this->game(2, 4),
        ]);

        $percentages = $this->predictor()->predict($teams, $played, $remaining);

        $this->assertEqualsWithDelta(100.0, array_sum($percentages), 0.5);
    }

    public function test_mathematically_decided_lead_yields_one_hundred_percent(): void
    {
        $teams = $this->teams();

        // Team 1 has 12 points; closest rival can reach at most 3 + 3 = 6.
        $played = collect([
            $this->game(1, 2, 3, 0),
            $this->game(1, 3, 3, 0),
            $this->game(1, 4, 3, 0),
            $this->game(2, 1, 0, 3),
            $this->game(2, 3, 0, 0),
            $this->game(3, 4, 0, 0),
        ]);

        $remaining = collect([
            $this->game(3, 1),
            $this->game(4, 2),
        ]);

        $percentages = $this->predictor()->predict($teams, $played, $remaining);

        $this->assertSame(100.0, $percentages[1]);
        $this->assertSame(0.0, $percentages[2]);
        $this->assertSame(0.0, $percentages[3]);
        $this->assertSame(0.0, $percentages[4]);
    }

    public function test_finished_season_gives_champion_exactly_one_hundred(): void
    {
        $teams = $this->teams();

        $played = collect([
            $this->game(1, 2, 3, 0),
            $this->game(3, 4, 0, 1),
        ]);

        $percentages = $this->predictor()->predict($teams, $played, collect());

        $this->assertSame(100.0, $percentages[1]);
        $this->assertSame(0.0, $percentages[2] + $percentages[3] + $percentages[4]);
    }

    public function test_stronger_team_carries_higher_odds_in_open_race(): void
    {
        $teams = $this->teams();

        // No games played yet: odds rest purely on simulated strength.
        $remaining = collect([
            $this->game(1, 4),
            $this->game(2, 3),
            $this->game(4, 1),
            $this->game(3, 2),
        ]);

        $percentages = $this->predictor()->predict($teams, collect(), $remaining);

        $this->assertGreaterThan($percentages[4], $percentages[1], 'Power 90 should out-odds power 60');
    }
}
