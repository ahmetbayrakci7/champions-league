<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Group;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Team;
use App\Services\Contracts\SuspensionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuspensionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SuspensionServiceInterface $service;

    private Team $team;

    private Team $rival;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SuspensionServiceInterface::class);
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

    private function card(Game $game, string $type, ?Player $player = null): void
    {
        MatchEvent::create([
            'game_id' => $game->id,
            'team_id' => $this->team->id,
            'player_id' => ($player ?? $this->player)->id,
            'minute' => 50,
            'type' => $type,
            'commentary' => 'test card',
        ]);
    }

    public function test_red_card_bans_the_player_for_the_next_match(): void
    {
        $first = $this->game(1);
        $this->card($first, 'red');

        $next = $this->game(2, played: false);

        $suspended = $this->service->suspendedPlayers($this->team, $next);

        $this->assertSame(['red card'], array_values($suspended));
        $this->assertArrayHasKey($this->player->id, $suspended);
    }

    public function test_ban_is_served_after_one_match(): void
    {
        $first = $this->game(1);
        $this->card($first, 'red');

        $this->game(2); // ban served here
        $third = $this->game(3, played: false);

        $this->assertSame([], $this->service->suspendedPlayers($this->team, $third));
    }

    public function test_third_accumulated_yellow_triggers_a_ban(): void
    {
        $this->card($this->game(1), 'yellow');
        $this->card($this->game(2), 'yellow');
        $this->card($this->game(3), 'yellow'); // 3rd yellow

        $fourth = $this->game(4, played: false);

        $suspended = $this->service->suspendedPlayers($this->team, $fourth);

        $this->assertArrayHasKey($this->player->id, $suspended);
        $this->assertSame('yellow card accumulation', $suspended[$this->player->id]);
    }

    public function test_old_yellows_do_not_ban_without_a_new_booking(): void
    {
        $this->card($this->game(1), 'yellow');
        $this->card($this->game(2), 'yellow');
        $this->card($this->game(3), 'yellow'); // banned for game 4
        $this->game(4);                        // served, no new card

        $fifth = $this->game(5, played: false);

        $this->assertSame([], $this->service->suspendedPlayers($this->team, $fifth));
    }

    public function test_two_yellows_are_not_enough(): void
    {
        $this->card($this->game(1), 'yellow');
        $this->card($this->game(2), 'yellow');

        $third = $this->game(3, played: false);

        $this->assertSame([], $this->service->suspendedPlayers($this->team, $third));
    }

    public function test_yellows_are_wiped_after_the_quarter_finals(): void
    {
        // Weeks: r16 = 7/8, qf = 9/10, sf = 11/12, final = 13.
        $this->card($this->game(7), 'yellow');
        $this->card($this->game(9), 'yellow');
        $this->card($this->game(11), 'yellow'); // SF leg 1 — 3rd overall, 1st after the reset

        $sfLegTwo = $this->game(12, played: false);

        $this->assertSame(
            [],
            $this->service->suspendedPlayers($this->team, $sfLegTwo),
            'Pre-semifinal yellows must not count after the QF amnesty',
        );
    }

    public function test_a_ban_earned_in_the_qf_second_leg_is_still_served_in_the_semis(): void
    {
        $this->card($this->game(7), 'yellow');
        $this->card($this->game(9), 'yellow');
        $this->card($this->game(10), 'yellow'); // 3rd yellow IN the QF second leg

        $sfLegOne = $this->game(11, played: false);

        $suspended = $this->service->suspendedPlayers($this->team, $sfLegOne);

        $this->assertArrayHasKey(
            $this->player->id,
            $suspended,
            'A suspension already imposed is not covered by the amnesty',
        );
    }

    public function test_red_cards_have_no_amnesty_before_the_final(): void
    {
        $semiLegTwo = $this->game(12);
        $this->card($semiLegTwo, 'red'); // sent off in the SF second leg

        $final = $this->game(13, played: false);

        $suspended = $this->service->suspendedPlayers($this->team, $final);

        $this->assertSame(['red card'], array_values($suspended), 'A red in the semis must cost the final');
    }

    public function test_suspended_players_do_not_appear_in_the_next_match(): void
    {
        // Full pipeline: a red card in week 1 keeps the player out of week 2.
        $first = $this->game(1);
        $this->card($first, 'red');

        // Give both teams squads so the engine fields lineups.
        Player::factory()->count(13)->for($this->team)->create();
        Player::factory()->count(13)->for($this->rival)->create();

        $next = $this->game(2, played: false);

        app(\App\Services\Contracts\LeagueServiceInterface::class)
            ->updateGame($next, 2, 1);

        $this->assertSame(
            0,
            $next->appearances()->where('player_id', $this->player->id)->count(),
            'Suspended player still appeared in the next match',
        );
    }
}
