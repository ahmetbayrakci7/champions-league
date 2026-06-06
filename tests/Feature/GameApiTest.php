<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsTournament;
use Tests\TestCase;

class GameApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTournament;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTournament();
        $this->postJson('/api/league/draw');
    }

    public function test_editing_a_played_game_recalculates_the_group_standings(): void
    {
        $this->postJson('/api/league/play-week');

        $game = Game::where('is_played', true)->first();

        $response = $this->putJson("/api/games/{$game->id}", [
            'home_goals' => 7,
            'away_goals' => 0,
        ]);

        $response->assertOk();

        $this->assertSame(7, $game->fresh()->home_goals);
        $this->assertSame(0, $game->fresh()->away_goals);

        $group = collect($response->json('groups'))
            ->firstWhere('id', $game->group_id);

        $homeRow = collect($group['standings'])->firstWhere('team_id', $game->home_team_id);

        $this->assertSame(3, $homeRow['points']);
        $this->assertSame(7, $homeRow['goals_for']);
    }

    public function test_unplayed_games_cannot_be_edited(): void
    {
        $game = Game::where('is_played', false)->first();

        $this->putJson("/api/games/{$game->id}", [
            'home_goals' => 1,
            'away_goals' => 1,
        ])->assertStatus(409);
    }

    public function test_scores_are_validated(): void
    {
        $this->postJson('/api/league/play-week');

        $game = Game::where('is_played', true)->first();

        $this->putJson("/api/games/{$game->id}", [
            'home_goals' => -1,
            'away_goals' => 'abc',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['home_goals', 'away_goals']);
    }
}
