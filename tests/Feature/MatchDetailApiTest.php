<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsTournament;
use Tests\TestCase;

class MatchDetailApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTournament;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTournament(withPlayers: true);
        $this->postJson('/api/league/draw');
    }

    public function test_unplayed_game_exposes_win_probabilities_but_no_lineups(): void
    {
        $game = Game::where('is_played', false)->first();

        $response = $this->getJson("/api/games/{$game->id}");

        $response->assertOk()
            ->assertJsonPath('game.is_played', false)
            ->assertJsonCount(0, 'events')
            ->assertJsonCount(0, 'lineups.home');

        $odds = $response->json('probabilities');

        $this->assertEqualsWithDelta(100.0, $odds['home'] + $odds['draw'] + $odds['away'], 0.2);
    }

    public function test_played_game_exposes_timeline_lineups_and_ratings(): void
    {
        $this->postJson('/api/league/play-week');

        $game = Game::where('is_played', true)->first();

        $response = $this->getJson("/api/games/{$game->id}");

        $response->assertOk()->assertJsonPath('game.is_played', true);

        foreach (['home', 'away'] as $side) {
            $lineup = collect($response->json("lineups.{$side}"));

            $this->assertSame(11, $lineup->where('is_starting', true)->count());

            foreach ($lineup as $row) {
                $this->assertNotNull($row['rating']);
                $this->assertGreaterThanOrEqual(2.0, $row['rating']);
                $this->assertLessThanOrEqual(10.0, $row['rating']);
            }
        }

        $goalEvents = collect($response->json('events'))->where('type', 'goal');
        $expectedGoals = $response->json('game.home_goals') + $response->json('game.away_goals');

        $this->assertCount($expectedGoals, $goalEvents, 'Timeline goals must match the scoreline');

        foreach ($response->json('events') as $event) {
            $this->assertNotSame('', $event['commentary']);
        }
    }

    public function test_stats_endpoint_builds_leaderboards_from_played_matches(): void
    {
        $this->postJson('/api/league/play-all');

        $response = $this->getJson('/api/stats');

        $response->assertOk()
            ->assertJsonPath('played_games', 96)
            ->assertJsonStructure([
                'players' => ['scorers', 'assists', 'contributions', 'ratings', 'cards'],
                'teams' => ['attack', 'defence', 'cards', 'clean_sheets', 'biggest_win'],
            ]);

        $this->assertNotEmpty($response->json('players.scorers'), 'Nobody scored across 96 games?');
        $this->assertNotEmpty($response->json('teams.attack'));

        $topScorer = $response->json('players.scorers.0');
        $this->assertGreaterThan(0, $topScorer['value']);
        $this->assertNotNull($topScorer['team']);
    }

    public function test_stats_endpoint_is_empty_before_any_match(): void
    {
        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('played_games', 0)
            ->assertJsonPath('players', null);
    }

    public function test_squad_endpoint_includes_tournament_form(): void
    {
        $this->postJson('/api/league/play-week');

        $game = Game::where('is_played', true)->first();

        $response = $this->getJson("/api/teams/{$game->home_team_id}");

        $response->assertOk();

        $players = collect($response->json('players'));
        $appeared = $players->where('apps', '>', 0);

        $this->assertGreaterThanOrEqual(11, $appeared->count());

        foreach ($appeared as $player) {
            $this->assertNotNull($player['avg_rating']);
            $this->assertLessThanOrEqual(10, $player['avg_rating']);
        }
    }
}
