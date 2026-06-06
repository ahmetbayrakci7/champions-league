<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Group;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsTournament;
use Tests\TestCase;

class LeagueApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTournament;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTournament();
    }

    public function test_state_before_the_draw_exposes_seeding_pots(): void
    {
        $response = $this->getJson('/api/league');

        $response->assertOk()
            ->assertJsonPath('drawn', false)
            ->assertJsonPath('season_over', false)
            ->assertJsonCount(4, 'pots')
            ->assertJsonCount(0, 'groups');

        foreach ($response->json('pots') as $pot) {
            $this->assertCount(8, $pot['teams']);
        }
    }

    public function test_draw_creates_eight_groups_with_valid_constraints(): void
    {
        $response = $this->postJson('/api/league/draw');

        $response->assertOk()
            ->assertJsonPath('drawn', true)
            ->assertJsonCount(8, 'groups')
            ->assertJsonPath('total_weeks', 6);

        $this->assertSame(8, Group::count());
        $this->assertSame(96, Game::count());

        $teams = Team::all()->keyBy('id');

        foreach ($response->json('groups') as $group) {
            $ids = collect($group['standings'])->pluck('team_id');

            $this->assertCount(4, $ids);

            $pots = $ids->map(fn ($id) => $teams[$id]->pot)->sort()->values()->all();
            $this->assertSame([1, 2, 3, 4], $pots, "Group {$group['name']} violates pot seeding");

            $countries = $ids->map(fn ($id) => $teams[$id]->country);
            $this->assertCount(4, $countries->unique(), "Group {$group['name']} has a same-country clash");
        }
    }

    public function test_fixture_splits_matchdays_between_tuesday_and_wednesday(): void
    {
        $this->postJson('/api/league/draw');

        $games = Game::all();

        $this->assertTrue($games->every(fn (Game $game) => $game->kickoff_at !== null));

        foreach (range(1, 6) as $week) {
            $weekGames = $games->where('week', $week);

            $tuesday = $weekGames->filter(fn (Game $game) => $game->kickoff_at->isTuesday());
            $wednesday = $weekGames->filter(fn (Game $game) => $game->kickoff_at->isWednesday());

            $this->assertSame(8, $tuesday->count(), "Matchday {$week}: expected 8 Tuesday games");
            $this->assertSame(8, $wednesday->count(), "Matchday {$week}: expected 8 Wednesday games");

            // Configured matchday date (Tuesday) drives the calendar.
            $expected = config('league.matchday_dates')[$week - 1];
            $this->assertSame($expected, $tuesday->first()->kickoff_at->toDateString());
        }

        // A group's two games share the day: one early-evening kickoff,
        // one late-evening — and never at unreasonable hours.
        foreach ($games->groupBy(fn (Game $game) => $game->group_id.'-'.$game->week) as $pair) {
            $this->assertCount(1, $pair->pluck('kickoff_at')->map->toDateString()->unique());
            $this->assertCount(2, $pair->pluck('kickoff_at')->map->format('H:i')->unique());
        }

        foreach ($games as $game) {
            $minutes = $game->kickoff_at->hour * 60 + $game->kickoff_at->minute;

            $this->assertGreaterThanOrEqual(17 * 60, $minutes, "Kickoff too early: {$game->kickoff_at}");
            $this->assertLessThanOrEqual(22 * 60, $minutes, "Kickoff too late: {$game->kickoff_at}");
        }
    }

    public function test_play_week_simulates_one_matchday_across_all_groups(): void
    {
        $this->postJson('/api/league/draw');

        $response = $this->postJson('/api/league/play-week');

        $response->assertOk()->assertJsonPath('current_week', 1);

        $this->assertSame(16, Game::where('is_played', true)->count());
        $this->assertSame(16, Game::where('week', 1)->where('is_played', true)->count());
    }

    public function test_play_all_finishes_the_group_stage_and_unlocks_predictions(): void
    {
        $this->postJson('/api/league/draw');

        $response = $this->postJson('/api/league/play-all');

        $response->assertOk()
            ->assertJsonPath('season_over', true)
            ->assertJsonPath('current_week', 6);

        $this->assertSame(0, Game::where('is_played', false)->count());

        foreach ($response->json('groups') as $group) {
            $predictions = $group['predictions'];

            $this->assertIsArray($predictions, "Group {$group['name']} has no predictions");
            $this->assertCount(4, $predictions);
            $this->assertEqualsWithDelta(100.0, array_sum($predictions), 0.5);

            // Group stage over: the winner must be certain.
            $this->assertContains(100.0, array_map('floatval', $predictions));
        }
    }

    public function test_predictions_stay_hidden_before_the_configured_matchday(): void
    {
        $this->postJson('/api/league/draw');

        $startWeek = (int) config('league.prediction_start_week');

        foreach (range(1, $startWeek - 1) as $week) {
            $this->postJson('/api/league/play-week');
        }

        $state = $this->getJson('/api/league');

        foreach ($state->json('groups') as $group) {
            $this->assertNull($group['predictions']);
        }

        $response = $this->postJson('/api/league/play-week');

        foreach ($response->json('groups') as $group) {
            $this->assertIsArray($group['predictions']);
        }
    }

    public function test_play_week_conflicts_when_the_stage_is_over(): void
    {
        $this->postJson('/api/league/draw');
        $this->postJson('/api/league/play-all');

        $this->postJson('/api/league/play-week')->assertStatus(409);
    }

    public function test_reset_returns_to_the_seeding_pots(): void
    {
        $this->postJson('/api/league/draw');
        $this->postJson('/api/league/play-all');

        $response = $this->postJson('/api/league/reset');

        $response->assertOk()
            ->assertJsonPath('drawn', false)
            ->assertJsonPath('current_week', 0)
            ->assertJsonPath('season_over', false)
            ->assertJsonCount(4, 'pots');

        $this->assertSame(0, Game::count());
        $this->assertSame(0, Team::whereNotNull('group_id')->count());
    }

    public function test_redraw_after_reset_produces_a_fresh_fixture(): void
    {
        $this->postJson('/api/league/draw');
        $this->postJson('/api/league/play-all');
        $this->postJson('/api/league/reset');

        $this->postJson('/api/league/draw')->assertOk()->assertJsonPath('drawn', true);

        $this->assertSame(96, Game::count());
        $this->assertSame(0, Game::where('is_played', true)->count());
    }
}
