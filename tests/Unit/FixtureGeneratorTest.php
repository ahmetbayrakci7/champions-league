<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Services\FixtureGenerator;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Tests\TestCase;

class FixtureGeneratorTest extends TestCase
{
    private FixtureGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new FixtureGenerator();
    }

    /**
     * @return Collection<int, Team>
     */
    private function teams(int $count = 4): Collection
    {
        return collect(range(1, $count))->map(function (int $id): Team {
            $team = new Team(['name' => "Team {$id}", 'code' => "T{$id}"]);
            $team->id = $id;

            return $team;
        });
    }

    public function test_four_teams_produce_twelve_matches_over_six_weeks(): void
    {
        $fixture = $this->generator->generate($this->teams());

        $this->assertCount(12, $fixture);
        $this->assertSame(range(1, 6), collect($fixture)->pluck('week')->unique()->sort()->values()->all());

        foreach (collect($fixture)->groupBy('week') as $games) {
            $this->assertCount(2, $games);
        }
    }

    public function test_every_team_plays_each_opponent_home_and_away(): void
    {
        $fixture = collect($this->generator->generate($this->teams()));

        foreach ([1, 2, 3, 4] as $teamId) {
            $homeOpponents = $fixture->where('home_team_id', $teamId)->pluck('away_team_id')->sort()->values()->all();
            $awayOpponents = $fixture->where('away_team_id', $teamId)->pluck('home_team_id')->sort()->values()->all();

            $expected = collect([1, 2, 3, 4])->reject(fn ($id) => $id === $teamId)->values()->all();

            $this->assertSame($expected, $homeOpponents, "Team {$teamId} home opponents mismatch");
            $this->assertSame($expected, $awayOpponents, "Team {$teamId} away opponents mismatch");
        }
    }

    public function test_no_team_plays_twice_in_the_same_week(): void
    {
        $fixture = collect($this->generator->generate($this->teams()));

        foreach ($fixture->groupBy('week') as $week => $games) {
            $ids = $games->flatMap(fn ($game) => [$game['home_team_id'], $game['away_team_id']]);

            $this->assertSame($ids->count(), $ids->unique()->count(), "Duplicate team in week {$week}");
        }
    }

    public function test_rejects_odd_team_counts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator->generate($this->teams(3));
    }
}
