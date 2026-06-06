<?php

namespace Tests\Feature;

use App\Models\Appearance;
use App\Models\Game;
use App\Models\Group;
use App\Models\Player;
use App\Models\Team;
use App\Services\Contracts\TeamFormServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamFormServiceTest extends TestCase
{
    use RefreshDatabase;

    private TeamFormServiceInterface $service;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TeamFormServiceInterface::class);
        $this->team = Team::factory()->create(['power' => 80]);
    }

    private function playedGameWithRatings(int $week, float $rating): void
    {
        $game = Game::factory()
            ->for(Group::factory())
            ->state([
                'week' => $week,
                'home_team_id' => $this->team->id,
                'away_team_id' => Team::factory()->create()->id,
                'home_goals' => 1,
                'away_goals' => 1,
                'is_played' => true,
            ])
            ->create();

        foreach (Player::factory()->count(3)->for($this->team)->create() as $player) {
            Appearance::create([
                'game_id' => $game->id,
                'team_id' => $this->team->id,
                'player_id' => $player->id,
                'is_starting' => true,
                'rating' => $rating,
            ]);
        }
    }

    public function test_no_matches_means_neutral_form(): void
    {
        $this->assertSame(1.0, $this->service->factor($this->team));
    }

    public function test_high_ratings_boost_the_power(): void
    {
        $this->playedGameWithRatings(1, 8.5);

        $this->assertGreaterThan(1.0, $this->service->factor($this->team));

        $this->service->adjust($this->team);

        $this->assertGreaterThan(80, $this->team->power);
    }

    public function test_poor_ratings_reduce_the_power(): void
    {
        $this->playedGameWithRatings(1, 4.0);

        $this->assertLessThan(1.0, $this->service->factor($this->team));

        $this->service->adjust($this->team);

        $this->assertLessThan(80, $this->team->power);
    }

    public function test_factor_is_clamped_to_the_configured_band(): void
    {
        $this->playedGameWithRatings(1, 10.0);
        $this->playedGameWithRatings(2, 10.0);

        $this->assertLessThanOrEqual((float) config('league.form.max'), $this->service->factor($this->team));

        $low = Team::factory()->create(['power' => 80]);
        $this->team = $low;
        $this->playedGameWithRatings(3, 2.0);

        $this->assertGreaterThanOrEqual((float) config('league.form.min'), $this->service->factor($low));
    }

    public function test_only_the_recent_window_counts(): void
    {
        // Three poor games push the factor down…
        $this->playedGameWithRatings(1, 4.0);
        $this->playedGameWithRatings(2, 4.0);
        $this->playedGameWithRatings(3, 4.0);

        $poor = $this->service->factor($this->team);

        // …then three strong games (window = 3) should fully recover it.
        $this->playedGameWithRatings(4, 8.5);
        $this->playedGameWithRatings(5, 8.5);
        $this->playedGameWithRatings(6, 8.5);

        $this->assertGreaterThan($poor, $this->service->factor($this->team));
        $this->assertGreaterThan(1.0, $this->service->factor($this->team));
    }
}
