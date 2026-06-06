<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Group;
use App\Models\Injury;
use App\Models\Player;
use App\Models\Team;
use App\Services\Contracts\InjuryServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InjuryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InjuryServiceInterface $service;

    private Team $team;

    private Team $rival;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InjuryServiceInterface::class);
        $this->team = Team::factory()->create();
        $this->rival = Team::factory()->create();
        $this->player = Player::factory()->for($this->team)->create();
    }

    private function game(int $week, bool $played = true): Game
    {
        return Game::factory()
            ->for(Group::factory())
            ->state([
                'week' => $week,
                'home_team_id' => $this->team->id,
                'away_team_id' => $this->rival->id,
                'home_goals' => $played ? 1 : null,
                'away_goals' => $played ? 0 : null,
                'is_played' => $played,
            ])
            ->create();
    }

    public function test_injured_player_misses_the_configured_number_of_matches(): void
    {
        $origin = $this->game(1);
        Injury::create(['game_id' => $origin->id, 'team_id' => $this->team->id, 'player_id' => $this->player->id, 'matches' => 2]);

        $second = $this->game(2, played: false);
        $third = $this->game(3, played: false);
        $fourth = $this->game(4, played: false);

        $this->assertSame([$this->player->id => 2], $this->service->injuredPlayers($this->team, $second));
        $this->assertSame([$this->player->id => 1], $this->service->injuredPlayers($this->team, $third));
        $this->assertSame([], $this->service->injuredPlayers($this->team, $fourth), 'Player should be fit again');
    }

    public function test_injury_does_not_affect_matches_before_it_happened(): void
    {
        $origin = $this->game(3);
        Injury::create(['game_id' => $origin->id, 'team_id' => $this->team->id, 'player_id' => $this->player->id, 'matches' => 1]);

        $earlier = $this->game(2, played: false);

        $this->assertSame([], $this->service->injuredPlayers($this->team, $earlier));
    }

    public function test_engine_injury_creates_a_persisted_lay_off(): void
    {
        // Squads so the engine fields lineups and can injure someone.
        Player::factory()->count(13)->for($this->team)->create();
        Player::factory()->count(13)->for($this->rival)->create();

        // Replay matches until an injury occurs (9% per team per match).
        $game = $this->game(1, played: false);
        $league = app(\App\Services\Contracts\LeagueServiceInterface::class);

        $found = null;

        for ($i = 0; $i < 60 && $found === null; $i++) {
            $league->updateGame($game, 1, 1);
            $found = Injury::first();
        }

        $this->assertNotNull($found, 'No injury in 60 simulated matches — chance looks broken');
        $this->assertGreaterThanOrEqual(1, $found->matches);
        $this->assertLessThanOrEqual(5, $found->matches);

        // The injured player went off in the match itself.
        $appearance = $game->appearances()->where('player_id', $found->player_id)->first();
        $this->assertNotNull($appearance->went_off);
    }
}
