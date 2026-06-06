<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Team;
use App\Models\Tie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsTournament;
use Tests\TestCase;

class KnockoutApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTournament;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTournament();
        $this->postJson('/api/league/draw');
    }

    public function test_knockout_cannot_start_before_the_group_stage_ends(): void
    {
        $this->postJson('/api/knockout/advance')->assertStatus(409);
    }

    public function test_r16_draw_pairs_winners_with_runners_up_under_constraints(): void
    {
        $this->postJson('/api/league/play-all');

        $response = $this->postJson('/api/knockout/advance');

        $response->assertOk()->assertJsonPath('drawn', true);

        $this->assertSame(8, Tie::where('stage', 'r16')->count());
        $this->assertSame(16, Game::where('stage', 'r16')->count());

        $teams = Team::all()->keyBy('id');

        foreach (Tie::where('stage', 'r16')->get() as $tie) {
            $home = $teams[$tie->home_team_id]; // runner-up hosts leg 1
            $away = $teams[$tie->away_team_id]; // group winner

            $this->assertNotSame($home->group_id, $away->group_id, 'R16 rematches a group');
            $this->assertNotSame($home->country, $away->country, 'R16 pairs same-country clubs');
        }

        // Every club appears exactly once across the round.
        $ids = Tie::where('stage', 'r16')->get()
            ->flatMap(fn (Tie $tie) => [$tie->home_team_id, $tie->away_team_id]);

        $this->assertCount(16, $ids->unique());
    }

    public function test_advancing_through_every_round_crowns_a_champion(): void
    {
        $this->postJson('/api/league/play-all');
        $this->postJson('/api/knockout/advance'); // draw

        // 2 legs each for R16, QF, SF + single final = 7 playing steps.
        foreach (range(1, 7) as $step) {
            $this->postJson('/api/knockout/advance')->assertOk();
        }

        $state = $this->getJson('/api/league')->json('knockout');

        $this->assertNotNull($state['champion'], 'No champion after the final');
        $this->assertSame('done', $state['next']['action']);

        $this->assertSame(15, Tie::count()); // 8 + 4 + 2 + 1
        $this->assertSame(0, Game::where('stage', '!=', 'group')->where('is_played', false)->count());
        $this->assertSame(1, Game::where('stage', 'final')->count());

        // Every decided tie names one of its two sides as the winner.
        foreach (Tie::all() as $tie) {
            $this->assertContains($tie->winner_team_id, [$tie->home_team_id, $tie->away_team_id]);
        }

        $finalTie = Tie::where('stage', 'final')->first();
        $this->assertSame($state['champion']['id'], $finalTie->winner_team_id);

        $this->postJson('/api/knockout/advance')->assertStatus(409);
    }

    public function test_advance_all_plays_the_whole_knockout_to_a_champion(): void
    {
        $this->postJson('/api/league/play-all');

        $response = $this->postJson('/api/knockout/advance-all');

        $response->assertOk();
        $this->assertNotNull($response->json('champion'), 'advance-all must crown a champion');
        $this->assertSame('done', $response->json('next.action'));

        $this->assertSame(15, Tie::count());
        $this->assertSame(0, Game::where('stage', '!=', 'group')->where('is_played', false)->count());
    }

    public function test_advance_all_finishes_from_a_mid_knockout_state(): void
    {
        $this->postJson('/api/league/play-all');
        $this->postJson('/api/knockout/advance'); // draw
        $this->postJson('/api/knockout/advance'); // R16 leg 1

        $response = $this->postJson('/api/knockout/advance-all');

        $response->assertOk();
        $this->assertNotNull($response->json('champion'));
    }

    public function test_advance_all_requires_a_finished_group_stage(): void
    {
        $this->postJson('/api/knockout/advance-all')->assertStatus(409);
    }

    public function test_knockout_results_cannot_be_edited(): void
    {
        $this->postJson('/api/league/play-all');
        $this->postJson('/api/knockout/advance'); // draw
        $this->postJson('/api/knockout/advance'); // leg 1

        $game = Game::where('stage', 'r16')->where('is_played', true)->first();

        $this->putJson("/api/games/{$game->id}", ['home_goals' => 9, 'away_goals' => 0])
            ->assertStatus(409);
    }
}
