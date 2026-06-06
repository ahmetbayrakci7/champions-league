<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_squad_endpoint_lists_players_best_first(): void
    {
        $team = Team::factory()->create(['name' => 'Test FC', 'power' => 80]);

        Player::factory()->for($team)->create(['name' => 'Average Joe', 'overall' => 70]);
        Player::factory()->for($team)->create(['name' => 'Star Player', 'overall' => 91]);
        Player::factory()->for($team)->create(['name' => 'Solid Sam', 'overall' => 82]);

        $response = $this->getJson("/api/teams/{$team->id}");

        $response->assertOk()
            ->assertJsonPath('team.name', 'Test FC')
            ->assertJsonCount(3, 'players')
            ->assertJsonPath('players.0.name', 'Star Player')
            ->assertJsonPath('players.1.name', 'Solid Sam')
            ->assertJsonPath('players.2.name', 'Average Joe');
    }

    public function test_unknown_team_returns_404(): void
    {
        $this->getJson('/api/teams/999')->assertNotFound();
    }
}
