<?php

namespace App\Services;

use App\DataTransferObjects\MatchResult;
use App\Models\Game;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Team;
use App\Services\Contracts\LineupSelectorInterface;
use App\Services\Contracts\MatchEngineInterface;
use App\Services\Contracts\MatchSimulatorInterface;
use App\Services\Contracts\RandomGeneratorInterface;
use App\Services\Support\SamplesPoisson;
use Illuminate\Support\Collection;

/**
 * OSM-style match engine.
 *
 * Builds on the Poisson xG model: picks both line-ups, lays out a
 * personnel timeline (bookings, injuries, substitutions), then drops
 * the goals onto it minute by minute with one-line commentary. Every
 * player who appears gets a 0-10 performance rating.
 */
class MatchEngine implements MatchEngineInterface
{
    use SamplesPoisson;

    private const GOAL_LINES = [
        '%s fires it into the bottom corner!',
        '%s heads home from close range!',
        'What a strike from %s — top bins!',
        '%s taps in at the far post!',
        'Composed finish by %s one-on-one!',
        '%s smashes it in off the underside of the bar!',
    ];

    private const YELLOW_LINES = [
        '%s goes into the book for a late challenge.',
        'Cynical foul — yellow card for %s.',
        '%s is cautioned for dissent.',
        'Shirt pull, and %s sees yellow.',
    ];

    private const RED_LINES = [
        'Straight red! %s is sent off!',
        'Horror tackle — %s sees a straight red card!',
    ];

    private const SECOND_YELLOW_LINES = [
        'Second yellow — %s has to go!',
        '%s is booked again and is off!',
    ];

    private const INJURY_LINES = [
        '%s pulls up and cannot continue.',
        'Trouble for %s — the physio signals a change.',
    ];

    private const SUB_LINES = [
        '%s comes on for %s.',
        'Fresh legs: %s replaces %s.',
    ];

    public function __construct(
        private readonly RandomGeneratorInterface $random,
        private readonly MatchSimulatorInterface $simulator,
        private readonly LineupSelectorInterface $lineups,
    ) {
    }

    public function play(Team $home, Team $away, ?array $fixedScore = null, bool $neutral = false, bool $extraTime = false): MatchResult
    {
        $duration = $extraTime ? 120 : 90;
        [$homeXg, $awayXg] = $this->simulator->expectedGoals($home, $away, $neutral);

        if ($extraTime) {
            $factor = 1 + (float) config('league.engine.extra_time_factor');
            $homeXg *= $factor;
            $awayXg *= $factor;
        }

        $sides = [
            'home' => $this->initSide($home, $fixedScore['home'] ?? $this->poisson($homeXg, $this->random)),
            'away' => $this->initSide($away, $fixedScore['away'] ?? $this->poisson($awayXg, $this->random)),
        ];

        $events = [];

        foreach (array_keys($sides) as $key) {
            $this->layOutCards($sides[$key], $events, $duration);
            $this->layOutInjury($sides[$key], $events, $duration);
            $this->layOutSubstitutions($sides[$key], $events, $duration);
            $this->layOutGoals($sides[$key], $events, $duration);
        }

        usort($events, fn (array $a, array $b): int => [$a['minute'], $a['id_seq']] <=> [$b['minute'], $b['id_seq']]);
        $events = array_map(function (array $event): array {
            unset($event['id_seq']);

            return $event;
        }, $events);

        $homeGoals = $sides['home']['goals'];
        $awayGoals = $sides['away']['goals'];

        $appearances = array_merge(
            $this->rateSide($sides['home'], $homeGoals, $awayGoals),
            $this->rateSide($sides['away'], $awayGoals, $homeGoals),
        );

        return new MatchResult($homeGoals, $awayGoals, $events, $appearances);
    }

    /**
     * Extra time only (level knockout tie): extends an already played
     * match with goals between 91' and 120' using the players who were
     * still on the pitch.
     *
     * @param  Collection<int, Player>  $homeOnPitch
     * @param  Collection<int, Player>  $awayOnPitch
     * @return array{home_goals: int, away_goals: int, events: array<int, array<string, mixed>>}
     */
    public function extraTime(Team $home, Team $away, Collection $homeOnPitch, Collection $awayOnPitch): array
    {
        [$homeXg, $awayXg] = $this->simulator->expectedGoals($home, $away);
        $factor = (float) config('league.engine.extra_time_factor');

        $events = [];
        $goals = [];

        foreach ([
            ['team' => $home, 'players' => $homeOnPitch, 'xg' => $homeXg * $factor, 'key' => 'home_goals'],
            ['team' => $away, 'players' => $awayOnPitch, 'xg' => $awayXg * $factor, 'key' => 'away_goals'],
        ] as $side) {
            $count = $this->poisson($side['xg'], $this->random);
            $goals[$side['key']] = $count;

            for ($i = 0; $i < $count; $i++) {
                $scorer = $this->pickWeighted($side['players'], fn (Player $p) => $this->scorerWeight($p));
                [$index, $template] = $this->pickLine(self::GOAL_LINES);

                $events[] = [
                    'minute' => 91 + (int) floor($this->random->float() * 30),
                    'type' => MatchEvent::TYPE_GOAL,
                    'team_id' => $side['team']->id,
                    'player_id' => $scorer?->id,
                    'related_player_id' => null,
                    'commentary' => 'Extra time! '.sprintf($template, $scorer?->name ?? $side['team']->name),
                    'template' => "goal.{$index}",
                    'params' => ['player' => $scorer?->name ?? $side['team']->name, 'et' => true],
                ];
            }
        }

        usort($events, fn (array $a, array $b): int => $a['minute'] <=> $b['minute']);

        return $goals + ['events' => $events];
    }

    /**
     * @return array<string, mixed>
     */
    private function initSide(Team $team, int $goals): array
    {
        $lineup = $this->lineups->select($team);

        $records = [];

        foreach ($lineup['starters'] as $player) {
            $records[$player->id] = $this->blankRecord($player, true);
        }

        return [
            'team' => $team,
            'goals' => $goals,
            'bench' => $lineup['bench'],
            'records' => $records,
            'subsLeft' => (int) config('league.engine.subs_per_team'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankRecord(Player $player, bool $starting, ?int $cameOn = null): array
    {
        return [
            'player' => $player,
            'is_starting' => $starting,
            'came_on' => $cameOn,
            'went_off' => null,
            'goals' => 0,
            'assists' => 0,
            'yellow' => 0,
            'yellow_minute' => null,
            'red' => false,
            'injured' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $side
     * @param  array<int, array<string, mixed>>  $events
     */
    private function layOutCards(array &$side, array &$events, int $duration): void
    {
        $yellowCount = $this->poisson((float) config('league.engine.yellow_mean_per_team'), $this->random);

        for ($i = 0; $i < min($yellowCount, 5); $i++) {
            $candidates = $this->playersOn($side, null)
                ->reject(fn (Player $p) => $side['records'][$p->id]['red']);

            $player = $this->pickWeighted($candidates, fn (Player $p) => $this->cardWeight($p));

            if ($player === null) {
                break;
            }

            $minute = 5 + (int) floor($this->random->float() * ($duration - 10));
            $record = &$side['records'][$player->id];
            $record['yellow']++;

            if ($record['yellow'] === 2) {
                // The second booking must come after the first one.
                $first = $record['yellow_minute'];
                $minute = min($duration, $first + 1 + (int) floor($this->random->float() * max(1, $duration - $first - 1)));

                $record['red'] = true;
                $record['went_off'] = $minute;
                [$index, $line] = $this->pickLine(self::SECOND_YELLOW_LINES);
                $this->pushEvent($events, $minute, MatchEvent::TYPE_RED, $side['team']->id, $player->id, null,
                    sprintf($line, $player->name),
                    ['template' => "yellow2.{$index}", 'params' => ['player' => $player->name]]);
            } else {
                $record['yellow_minute'] = $minute;
                [$index, $line] = $this->pickLine(self::YELLOW_LINES);
                $this->pushEvent($events, $minute, MatchEvent::TYPE_YELLOW, $side['team']->id, $player->id, null,
                    sprintf($line, $player->name),
                    ['template' => "yellow.{$index}", 'params' => ['player' => $player->name]]);
            }
        }

        if ($this->random->float() < (float) config('league.engine.direct_red_chance')) {
            $candidates = $this->playersOn($side, null)
                ->reject(fn (Player $p) => $side['records'][$p->id]['red']);

            $player = $this->pickWeighted($candidates, fn (Player $p) => $this->cardWeight($p));

            if ($player !== null) {
                $minute = 25 + (int) floor($this->random->float() * ($duration - 30));
                $side['records'][$player->id]['red'] = true;
                $side['records'][$player->id]['went_off'] = $minute;
                [$index, $line] = $this->pickLine(self::RED_LINES);
                $this->pushEvent($events, $minute, MatchEvent::TYPE_RED, $side['team']->id, $player->id, null,
                    sprintf($line, $player->name),
                    ['template' => "red.{$index}", 'params' => ['player' => $player->name]]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $side
     * @param  array<int, array<string, mixed>>  $events
     */
    private function layOutInjury(array &$side, array &$events, int $duration): void
    {
        if ($this->random->float() >= (float) config('league.engine.injury_chance')) {
            return;
        }

        $minute = 10 + (int) floor($this->random->float() * min(70, $duration - 20));

        $candidates = $this->playersOn($side, $minute);
        $player = $candidates->isEmpty()
            ? null
            : $candidates[(int) floor($this->random->float() * $candidates->count())];

        if ($player === null) {
            return;
        }

        $record = &$side['records'][$player->id];
        $record['injured'] = true;
        $record['went_off'] = $minute;

        $matches = $this->injuryDuration();
        [$index, $line] = $this->pickLine(self::INJURY_LINES);
        $text = sprintf($line, $player->name)
            .sprintf(' Sidelined for %d match%s.', $matches, $matches === 1 ? '' : 'es');

        $this->pushEvent(
            $events, $minute, MatchEvent::TYPE_INJURY, $side['team']->id, $player->id, null, $text,
            [
                'injury_matches' => $matches,
                'template' => "injury.{$index}",
                'params' => ['player' => $player->name, 'matches' => $matches],
            ],
        );

        $this->bringOn($side, $events, $minute, $player);
    }

    /**
     * Mostly short knocks (1-2 games), the odd longer lay-off (4-5).
     */
    private function injuryDuration(): int
    {
        $roll = $this->random->float();

        return match (true) {
            $roll < 0.45 => 1,
            $roll < 0.80 => 2,
            $roll < 0.92 => 3,
            $roll < 0.98 => 4,
            default => 5,
        };
    }

    /**
     * @param  array<string, mixed>  $side
     * @param  array<int, array<string, mixed>>  $events
     */
    private function layOutSubstitutions(array &$side, array &$events, int $duration): void
    {
        $planned = min($side['subsLeft'], $this->random->float() < 0.6 ? 3 : 2);

        for ($i = 0; $i < $planned; $i++) {
            $minute = 55 + (int) floor($this->random->float() * min(30, $duration - 60));

            $candidates = $this->playersOn($side, $minute)
                ->reject(fn (Player $p) => $p->position_type === 'goalkeeper')
                ->reject(fn (Player $p) => $side['records'][$p->id]['came_on'] !== null)
                ->values();

            if ($candidates->isEmpty()) {
                return;
            }

            $off = $candidates[(int) floor($this->random->float() * $candidates->count())];

            $side['records'][$off->id]['went_off'] = $minute;

            if (! $this->bringOn($side, $events, $minute, $off)) {
                return;
            }
        }
    }

    /**
     * Sub in the strongest unused bench player (same position type when
     * possible). Returns false when no substitution could be made.
     *
     * @param  array<string, mixed>  $side
     * @param  array<int, array<string, mixed>>  $events
     */
    private function bringOn(array &$side, array &$events, int $minute, Player $off): bool
    {
        if ($side['subsLeft'] <= 0) {
            return false;
        }

        $available = $side['bench']->reject(fn (Player $p) => isset($side['records'][$p->id]))->values();

        if ($available->isEmpty()) {
            return false;
        }

        $replacement = $available->firstWhere('position_type', $off->position_type) ?? $available->first();

        $side['records'][$replacement->id] = $this->blankRecord($replacement, false, $minute);
        $side['subsLeft']--;

        [$index, $line] = $this->pickLine(self::SUB_LINES);
        $this->pushEvent($events, $minute, MatchEvent::TYPE_SUB, $side['team']->id, $replacement->id, $off->id,
            sprintf($line, $replacement->name, $off->name),
            ['template' => "sub.{$index}", 'params' => ['in' => $replacement->name, 'out' => $off->name]]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $side
     * @param  array<int, array<string, mixed>>  $events
     */
    private function layOutGoals(array &$side, array &$events, int $duration): void
    {
        for ($i = 0; $i < $side['goals']; $i++) {
            $minute = 1 + (int) floor($this->random->float() * $duration);

            $onPitch = $this->playersOn($side, $minute);
            $scorer = $this->pickWeighted($onPitch, fn (Player $p) => $this->scorerWeight($p));

            $assister = null;

            if ($scorer !== null && $this->random->float() < (float) config('league.engine.assist_chance')) {
                $assister = $this->pickWeighted(
                    $onPitch->reject(fn (Player $p) => $p->id === $scorer->id),
                    fn (Player $p) => max(10, $p->passing ?? $p->overall),
                );
            }

            if ($scorer !== null) {
                $side['records'][$scorer->id]['goals']++;
            }

            if ($assister !== null) {
                $side['records'][$assister->id]['assists']++;
            }

            [$index, $template] = $this->pickLine(self::GOAL_LINES);
            $line = sprintf($template, $scorer?->name ?? $side['team']->name);

            if ($assister !== null) {
                $line .= " ({$assister->name} with the assist)";
            }

            $this->pushEvent($events, $minute, MatchEvent::TYPE_GOAL, $side['team']->id, $scorer?->id, $assister?->id, $line, [
                'template' => "goal.{$index}",
                'params' => [
                    'player' => $scorer?->name ?? $side['team']->name,
                    'assist' => $assister?->name,
                ],
            ]);
        }
    }

    /**
     * Players on the pitch at a given minute (null = ever started).
     *
     * @param  array<string, mixed>  $side
     * @return Collection<int, Player>
     */
    private function playersOn(array $side, ?int $minute): Collection
    {
        return collect($side['records'])
            ->filter(function (array $record) use ($minute): bool {
                if ($minute === null) {
                    return $record['is_starting'];
                }

                $on = $record['came_on'] === null || $record['came_on'] <= $minute;
                $stillOn = $record['went_off'] === null || $record['went_off'] > $minute;

                return $on && $stillOn;
            })
            ->map(fn (array $record) => $record['player'])
            ->values();
    }

    /**
     * Performance ratings: base 6.0 shaped by contributions, result,
     * defensive record, discipline and a pinch of noise. Clamped to 10.
     *
     * @param  array<string, mixed>  $side
     * @return array<int, array<string, mixed>>
     */
    private function rateSide(array $side, int $scored, int $conceded): array
    {
        $rows = [];

        foreach ($side['records'] as $record) {
            $rows[] = [
                'team_id' => $side['team']->id,
                'player_id' => $record['player']->id,
                'is_starting' => $record['is_starting'],
                'came_on' => $record['came_on'],
                'went_off' => $record['went_off'],
                'rating' => $this->ratePlayer($record, $scored, $conceded),
            ];
        }

        return $rows;
    }

    /**
     * Performance rating for one player: base 6.0 shaped by goals,
     * assists, result, defensive record, discipline, class and noise.
     *
     * @param  array<string, mixed>  $record
     */
    private function ratePlayer(array $record, int $scored, int $conceded): float
    {
        /** @var Player $player */
        $player = $record['player'];

        $rating = 6.0;
        $rating += $record['goals'] * 1.05;
        $rating += $record['assists'] * 0.65;
        $rating += match (true) {
            $scored > $conceded => 0.25,
            $scored < $conceded => -0.25,
            default => 0.0,
        };

        $defensive = in_array($player->position_type, ['goalkeeper', 'defense'], true);

        if ($defensive) {
            $rating += $conceded === 0 ? 0.55 : -0.15 * $conceded;
        }

        $rating -= $record['yellow'] * 0.3;
        $rating -= $record['red'] ? 1.2 : 0.0;
        $rating -= $record['injured'] ? 0.2 : 0.0;
        $rating += ($player->overall - 76) / 40; // class shines through
        $rating += ($this->random->float() - 0.45) * 1.2;

        // Cameo appearances drift back towards the baseline.
        $minutes = ($record['went_off'] ?? 90) - ($record['came_on'] ?? 0);

        if ($minutes < 30) {
            $rating = 6.0 + ($rating - 6.0) * 0.6;
        }

        return round(max(2.0, min(10.0, $rating)), 1);
    }

    /**
     * Re-build a played match for a manually edited score: the booking,
     * injury and substitution events (and who appeared) are kept exactly
     * as they were — only the goals are regenerated to match the new
     * scoreline, and ratings recomputed from the new result.
     */
    public function rescore(Game $game, int $homeGoals, int $awayGoals): MatchResult
    {
        $game->loadMissing(['appearances.player', 'events']);

        // Rebuild per-player records from the stored appearances; fold in
        // the kept cards/injuries so ratings still reflect them.
        $records = [];

        foreach ($game->appearances as $appearance) {
            if ($appearance->player === null) {
                continue;
            }

            $records[$appearance->player_id] = [
                'player' => $appearance->player,
                'team_id' => $appearance->team_id,
                'is_starting' => (bool) $appearance->is_starting,
                'came_on' => $appearance->came_on,
                'went_off' => $appearance->went_off,
                'goals' => 0,
                'assists' => 0,
                'yellow' => 0,
                'red' => false,
                'injured' => false,
            ];
        }

        $events = [];

        foreach ($game->events as $event) {
            if ($event->type === MatchEvent::TYPE_GOAL) {
                continue; // goals are regenerated below
            }

            if (isset($records[$event->player_id])) {
                match ($event->type) {
                    MatchEvent::TYPE_YELLOW => $records[$event->player_id]['yellow']++,
                    MatchEvent::TYPE_RED => $records[$event->player_id]['red'] = true,
                    MatchEvent::TYPE_INJURY => $records[$event->player_id]['injured'] = true,
                    default => null,
                };
            }

            $events[] = [
                'minute' => $event->minute,
                'type' => $event->type,
                'team_id' => $event->team_id,
                'player_id' => $event->player_id,
                'related_player_id' => $event->related_player_id,
                'commentary' => $event->commentary,
                'template' => $event->template,
                'params' => $event->params,
            ];
        }

        $this->layOutEditedGoals($records, $game->home_team_id, $homeGoals, $events);
        $this->layOutEditedGoals($records, $game->away_team_id, $awayGoals, $events);

        usort($events, fn (array $a, array $b): int => ($a['minute'] <=> $b['minute']));

        $appearances = [];

        foreach ($records as $record) {
            $scored = $record['team_id'] === $game->home_team_id ? $homeGoals : $awayGoals;
            $conceded = $record['team_id'] === $game->home_team_id ? $awayGoals : $homeGoals;

            $appearances[] = [
                'team_id' => $record['team_id'],
                'player_id' => $record['player']->id,
                'is_starting' => $record['is_starting'],
                'came_on' => $record['came_on'],
                'went_off' => $record['went_off'],
                'rating' => $this->ratePlayer($record, $scored, $conceded),
            ];
        }

        return new MatchResult($homeGoals, $awayGoals, $events, $appearances);
    }

    /**
     * Distribute a team's edited goal count across the players who were
     * on the pitch, mirroring the live engine's scorer/assist weighting.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, array<string, mixed>>  $events
     */
    private function layOutEditedGoals(array &$records, int $teamId, int $goals, array &$events): void
    {
        for ($i = 0; $i < $goals; $i++) {
            $minute = 1 + (int) floor($this->random->float() * 90);

            $onPitch = collect($records)
                ->filter(fn (array $r) => $r['team_id'] === $teamId)
                ->filter(function (array $r) use ($minute): bool {
                    $on = $r['came_on'] === null || $r['came_on'] <= $minute;
                    $stillOn = $r['went_off'] === null || $r['went_off'] > $minute;

                    return $on && $stillOn;
                })
                ->map(fn (array $r) => $r['player'])
                ->values();

            $scorer = $this->pickWeighted($onPitch, fn (Player $p) => $this->scorerWeight($p));

            $assister = null;

            if ($scorer !== null && $this->random->float() < (float) config('league.engine.assist_chance')) {
                $assister = $this->pickWeighted(
                    $onPitch->reject(fn (Player $p) => $p->id === $scorer->id),
                    fn (Player $p) => max(10, $p->passing ?? $p->overall),
                );
            }

            if ($scorer !== null) {
                $records[$scorer->id]['goals']++;
            }

            if ($assister !== null) {
                $records[$assister->id]['assists']++;
            }

            [$index, $template] = $this->pickLine(self::GOAL_LINES);
            $line = sprintf($template, $scorer?->name ?? 'The team');

            if ($assister !== null) {
                $line .= " ({$assister->name} with the assist)";
            }

            $events[] = [
                'minute' => $minute,
                'type' => MatchEvent::TYPE_GOAL,
                'team_id' => $teamId,
                'player_id' => $scorer?->id,
                'related_player_id' => $assister?->id,
                'commentary' => $line,
                'template' => "goal.{$index}",
                'params' => ['player' => $scorer?->name, 'assist' => $assister?->name],
            ];
        }
    }

    private function scorerWeight(Player $player): float
    {
        $positional = match ($player->position_type) {
            'attack' => 5.0,
            'midfielder' => 2.4,
            'defense' => 0.8,
            default => 0.05,
        };

        return $positional * max(20, $player->shooting ?? $player->overall);
    }

    private function cardWeight(Player $player): float
    {
        return match ($player->position_type) {
            'defense' => 3.0,
            'midfielder' => 2.0,
            'attack' => 1.2,
            default => 0.3,
        };
    }

    /**
     * @param  Collection<int, Player>  $players
     */
    private function pickWeighted(Collection $players, callable $weight): ?Player
    {
        if ($players->isEmpty()) {
            return null;
        }

        $weights = $players->map(fn (Player $p) => max(0.01, (float) $weight($p)));
        $target = $this->random->float() * $weights->sum();

        foreach ($players as $index => $player) {
            $target -= $weights[$index];

            if ($target <= 0) {
                return $player;
            }
        }

        return $players->last();
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function pick(array $lines): string
    {
        return $lines[(int) floor($this->random->float() * count($lines))];
    }

    /**
     * Pick a line plus its index so the frontend can re-render the
     * sentence from a translation template.
     *
     * @param  array<int, string>  $lines
     * @return array{0: int, 1: string}
     */
    private function pickLine(array $lines): array
    {
        $index = (int) floor($this->random->float() * count($lines));

        return [$index, $lines[$index]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<string, mixed>  $extra  engine-only metadata (e.g. injury_matches)
     */
    private function pushEvent(array &$events, int $minute, string $type, int $teamId, ?int $playerId, ?int $relatedId, string $commentary, array $extra = []): void
    {
        $events[] = $extra + [
            'id_seq' => count($events),
            'minute' => min($minute, 120),
            'type' => $type,
            'team_id' => $teamId,
            'player_id' => $playerId,
            'related_player_id' => $relatedId,
            'commentary' => $commentary,
        ];
    }
}
