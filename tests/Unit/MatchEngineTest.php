<?php

namespace Tests\Unit;

use App\Models\Player;
use App\Models\Team;
use App\Services\LineupSelector;
use App\Services\MatchEngine;
use App\Services\MatchSimulator;
use App\Services\Support\MtRandomGenerator;
use Tests\TestCase;

class MatchEngineTest extends TestCase
{
    private MatchEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $random = new MtRandomGenerator();

        $this->engine = new MatchEngine(
            $random,
            new MatchSimulator($random),
            new LineupSelector(),
        );
    }

    private function team(int $id, int $power = 80): Team
    {
        $team = new Team([
            'name' => "Team {$id}",
            'code' => "T{$id}",
            'power' => $power,
            'home_advantage' => 10,
            'supporter_strength' => 80,
            'goalkeeper_factor' => 80,
        ]);
        $team->id = $id;

        $squad = collect();
        $playerId = $id * 100;

        $blueprint = array_merge(
            [['GK', 'goalkeeper'], ['GK', 'goalkeeper']],
            array_fill(0, 5, ['CB', 'defense']),
            array_fill(0, 5, ['CM', 'midfielder']),
            array_fill(0, 4, ['ST', 'attack']),
        );

        foreach ($blueprint as $index => [$position, $type]) {
            $player = new Player([
                'name' => "Player {$id}-{$index}",
                'position' => $position,
                'position_type' => $type,
                'overall' => 70 + ($index % 15),
                'shooting' => 65 + ($index % 20),
                'passing' => 65 + ($index % 20),
            ]);
            $player->id = ++$playerId;
            $player->team_id = $id;
            $squad->push($player);
        }

        $team->setRelation('players', $squad);

        return $team;
    }

    public function test_fixed_score_is_honoured_and_goal_events_match_it(): void
    {
        $result = $this->engine->play($this->team(1), $this->team(2), ['home' => 3, 'away' => 2]);

        $this->assertSame(3, $result->homeGoals);
        $this->assertSame(2, $result->awayGoals);

        $goalEvents = array_filter($result->events, fn (array $event) => $event['type'] === 'goal');

        $this->assertCount(5, $goalEvents);
    }

    public function test_both_sides_field_eleven_starters(): void
    {
        $result = $this->engine->play($this->team(1), $this->team(2));

        foreach ([1, 2] as $teamId) {
            $starters = array_filter(
                $result->appearances,
                fn (array $row) => $row['team_id'] === $teamId && $row['is_starting'],
            );

            $this->assertCount(11, $starters, "Team {$teamId} did not start 11 players");
        }
    }

    public function test_ratings_stay_within_bounds(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $result = $this->engine->play($this->team(1), $this->team(2));

            foreach ($result->appearances as $row) {
                $this->assertGreaterThanOrEqual(2.0, $row['rating']);
                $this->assertLessThanOrEqual(10.0, $row['rating']);
            }
        }
    }

    public function test_substitutes_carry_their_entry_minute(): void
    {
        $found = false;

        for ($i = 0; $i < 10 && ! $found; $i++) {
            $result = $this->engine->play($this->team(1), $this->team(2));

            foreach ($result->appearances as $row) {
                if (! $row['is_starting']) {
                    $found = true;
                    $this->assertNotNull($row['came_on']);
                    $this->assertGreaterThan(0, $row['came_on']);
                }
            }
        }

        $this->assertTrue($found, 'No substitution happened across 10 simulated matches');
    }

    public function test_every_event_references_a_known_player_or_none(): void
    {
        $result = $this->engine->play($this->team(1), $this->team(2));

        $known = array_column($result->appearances, 'player_id');

        foreach ($result->events as $event) {
            if ($event['player_id'] !== null) {
                $this->assertContains($event['player_id'], $known, "Event references unknown player: {$event['commentary']}");
            }

            $this->assertNotSame('', $event['commentary']);
            $this->assertGreaterThanOrEqual(1, $event['minute']);
            $this->assertLessThanOrEqual(120, $event['minute']);

            // Localisation contract: every event carries a template key
            // and name params so the frontend can rebuild the sentence.
            $this->assertMatchesRegularExpression('/^(goal|yellow|yellow2|red|injury|sub)\.\d+$/', $event['template']);
            $this->assertIsArray($event['params']);
        }
    }

    public function test_extra_time_produces_late_goals_only(): void
    {
        $home = $this->team(1, 95);
        $away = $this->team(2, 95);

        $sampled = false;

        for ($i = 0; $i < 30 && ! $sampled; $i++) {
            $extra = $this->engine->extraTime($home, $away, $home->players->take(11), $away->players->take(11));

            foreach ($extra['events'] as $event) {
                $sampled = true;
                $this->assertGreaterThanOrEqual(91, $event['minute']);
                $this->assertLessThanOrEqual(120, $event['minute']);
                $this->assertSame('goal', $event['type']);
            }
        }

        $this->assertTrue($sampled, 'No extra-time goal in 30 attempts — xG factor looks broken');
    }

    public function test_playerless_teams_still_produce_a_result(): void
    {
        $home = $this->team(1);
        $away = $this->team(2);
        $home->setRelation('players', collect());
        $away->setRelation('players', collect());

        $result = $this->engine->play($home, $away, ['home' => 2, 'away' => 1]);

        $this->assertSame(2, $result->homeGoals);
        $this->assertSame([], $result->appearances);

        foreach ($result->events as $event) {
            $this->assertNull($event['player_id']);
        }
    }
}
