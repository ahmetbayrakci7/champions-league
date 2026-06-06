<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Services\StandingsCalculator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class StandingsCalculatorTest extends TestCase
{
    private StandingsCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new StandingsCalculator();
    }

    /**
     * @return Collection<int, Team>
     */
    private function teams(): Collection
    {
        return collect([
            ['id' => 1, 'name' => 'Alpha'],
            ['id' => 2, 'name' => 'Bravo'],
            ['id' => 3, 'name' => 'Charlie'],
            ['id' => 4, 'name' => 'Delta'],
        ])->map(function (array $row): Team {
            $team = new Team(['name' => $row['name'], 'code' => strtoupper(substr($row['name'], 0, 3))]);
            $team->id = $row['id'];

            return $team;
        });
    }

    public function test_win_draw_and_loss_award_premier_league_points(): void
    {
        $standings = $this->calculator->calculate($this->teams(), [
            ['home_team_id' => 1, 'away_team_id' => 2, 'home_goals' => 2, 'away_goals' => 0],
            ['home_team_id' => 3, 'away_team_id' => 4, 'home_goals' => 1, 'away_goals' => 1],
        ]);

        $byTeam = collect($standings)->keyBy('teamId');

        $this->assertSame(3, $byTeam[1]->points());
        $this->assertSame(0, $byTeam[2]->points());
        $this->assertSame(1, $byTeam[3]->points());
        $this->assertSame(1, $byTeam[4]->points());
    }

    public function test_orders_by_points_first(): void
    {
        $standings = $this->calculator->calculate($this->teams(), [
            ['home_team_id' => 2, 'away_team_id' => 1, 'home_goals' => 1, 'away_goals' => 0],
            ['home_team_id' => 3, 'away_team_id' => 4, 'home_goals' => 2, 'away_goals' => 2],
        ]);

        $this->assertSame(2, $standings[0]->teamId);
    }

    public function test_breaks_point_ties_by_goal_difference_then_goals_scored(): void
    {
        $standings = $this->calculator->calculate($this->teams(), [
            // Team 1 wins 4-0 (GD +4), Team 2 wins 1-0 (GD +1) -> both 3 pts
            ['home_team_id' => 1, 'away_team_id' => 3, 'home_goals' => 4, 'away_goals' => 0],
            ['home_team_id' => 2, 'away_team_id' => 4, 'home_goals' => 1, 'away_goals' => 0],
        ]);

        $this->assertSame(1, $standings[0]->teamId, 'Better GD should rank first');
        $this->assertSame(2, $standings[1]->teamId);

        $standings = $this->calculator->calculate($this->teams(), [
            // Equal points and GD; Team 2 scored more goals (3-1 vs 2-0)
            ['home_team_id' => 1, 'away_team_id' => 3, 'home_goals' => 2, 'away_goals' => 0],
            ['home_team_id' => 2, 'away_team_id' => 4, 'home_goals' => 3, 'away_goals' => 1],
        ]);

        $this->assertSame(2, $standings[0]->teamId, 'More goals scored should break equal GD');
    }

    public function test_accumulates_full_record_across_matches(): void
    {
        $standings = $this->calculator->calculate($this->teams(), [
            ['home_team_id' => 1, 'away_team_id' => 2, 'home_goals' => 2, 'away_goals' => 1],
            ['home_team_id' => 2, 'away_team_id' => 1, 'home_goals' => 0, 'away_goals' => 0],
            ['home_team_id' => 1, 'away_team_id' => 3, 'home_goals' => 0, 'away_goals' => 3],
        ]);

        $alpha = collect($standings)->keyBy('teamId')[1];

        $this->assertSame(3, $alpha->played);
        $this->assertSame(1, $alpha->won);
        $this->assertSame(1, $alpha->drawn);
        $this->assertSame(1, $alpha->lost);
        $this->assertSame(2, $alpha->goalsFor);
        $this->assertSame(4, $alpha->goalsAgainst);
        $this->assertSame(-2, $alpha->goalDifference());
        $this->assertSame(4, $alpha->points());
    }

    public function test_empty_results_produce_zeroed_table(): void
    {
        $standings = $this->calculator->calculate($this->teams(), []);

        $this->assertCount(4, $standings);

        foreach ($standings as $row) {
            $this->assertSame(0, $row->played);
            $this->assertSame(0, $row->points());
        }
    }
}
