<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Group;
use App\Models\Team;
use App\Models\Tie;
use App\Services\Contracts\KnockoutServiceInterface;
use App\Services\Contracts\MatchEngineInterface;
use App\Services\Contracts\RandomGeneratorInterface;
use App\Services\Contracts\StandingsCalculatorInterface;
use App\Services\Contracts\InjuryServiceInterface;
use App\Services\Contracts\SuspensionServiceInterface;
use App\Services\Contracts\TeamFormServiceInterface;
use App\Services\MatchEngine;
use App\Services\Support\PersistsMatchResults;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Knockout phase (FAQ #3): group winners + runners-up → two-legged
 * R16/QF/SF and a single-match final on neutral ground. Level ties go
 * to extra time, then a penalty shoot-out.
 */
class KnockoutService implements KnockoutServiceInterface
{
    use PersistsMatchResults;

    private const STAGE_LABELS = [
        'r16' => 'ROUND OF 16',
        'qf' => 'QUARTER-FINALS',
        'sf' => 'SEMI-FINALS',
        'final' => 'FINAL',
    ];

    public function __construct(
        private readonly MatchEngineInterface $engine,
        private readonly StandingsCalculatorInterface $standings,
        private readonly RandomGeneratorInterface $random,
        private readonly SuspensionServiceInterface $suspensions,
        private readonly InjuryServiceInterface $injuries,
        private readonly TeamFormServiceInterface $form,
    ) {
    }

    public function state(): array
    {
        $available = $this->groupStageOver();
        $ties = Tie::with([
            'homeTeam:id,name,code,color,logo_url,country',
            'awayTeam:id,name,code,color,logo_url,country',
            'games' => fn ($query) => $query->with([
                'homeTeam:id,name,code,color,logo_url',
                'awayTeam:id,name,code,color,logo_url',
            ]),
        ])->orderBy('position')->get();

        $champion = null;

        if (($finalTie = $ties->firstWhere('stage', 'final'))?->winner_team_id) {
            $champion = Team::select('id', 'name', 'code', 'color', 'logo_url')
                ->find($finalTie->winner_team_id);
        }

        return [
            'available' => $available,
            'drawn' => $ties->isNotEmpty(),
            'champion' => $champion,
            'next' => $this->nextAction($available, $ties),
            'stages' => collect(self::STAGE_LABELS)
                ->map(fn (string $label, string $stage) => [
                    'stage' => $stage,
                    'label' => $label,
                    'ties' => $ties->where('stage', $stage)->values()->map(fn (Tie $tie) => $this->tiePayload($tie)),
                ])
                ->filter(fn (array $payload) => $payload['ties']->isNotEmpty())
                ->values(),
        ];
    }

    public function advance(): void
    {
        if (! $this->groupStageOver()) {
            throw new RuntimeException('The group stage is not finished yet.');
        }

        if (Tie::count() === 0) {
            $this->drawRoundOf16();

            return;
        }

        $week = Game::where('stage', '!=', 'group')->where('is_played', false)->min('week');

        if ($week === null) {
            throw new RuntimeException('The tournament is already decided.');
        }

        DB::transaction(function () use ($week): void {
            $this->playKnockoutWeek((int) $week);
            $this->resolveFinishedTies();
            $this->seedNextStage();
        });
    }

    /**
     * R16 draw: group winners host leg 2, runners-up host leg 1; no
     * rematch of the group, no same-association pairing (FAQ #4 spirit).
     */
    private function drawRoundOf16(): void
    {
        $teams = Team::whereNotNull('group_id')->get();
        $games = Game::where('stage', 'group')->get();

        $winners = [];
        $runners = [];

        foreach (Group::orderBy('name')->get() as $group) {
            $table = $this->standings->calculate(
                $teams->where('group_id', $group->id)->values(),
                $games->where('group_id', $group->id)->where('is_played', true)->values(),
            );

            $winners[] = ['team' => $teams->firstWhere('id', $table[0]->teamId), 'group' => $group->name];
            $runners[] = ['team' => $teams->firstWhere('id', $table[1]->teamId), 'group' => $group->name];
        }

        $pairing = $this->pairRound($winners, $runners);

        DB::transaction(function () use ($pairing): void {
            foreach ($pairing as $position => [$winner, $runner]) {
                // Runner-up hosts the first leg, group winner the second.
                $this->createTie('r16', $position + 1, $runner['team'], $winner['team']);
            }
        });
    }

    /**
     * Backtracking assignment honouring the no-same-group and
     * no-same-country constraints; runners are shuffled for variety.
     *
     * @param  array<int, array{team: Team, group: string}>  $winners
     * @param  array<int, array{team: Team, group: string}>  $runners
     * @return array<int, array{0: array{team: Team, group: string}, 1: array{team: Team, group: string}}>
     */
    private function pairRound(array $winners, array $runners): array
    {
        $runners = $this->shuffle($runners);

        $assignment = $this->matchWinners($winners, $runners, []);

        if ($assignment === null) {
            // Constraint-free fallback keeps the tournament moving.
            $assignment = array_map(null, array_keys($winners), array_keys($runners));
            $assignment = array_map(fn ($pair) => [$pair[0], $pair[1]], $assignment);
        }

        return array_map(
            fn (array $pair) => [$winners[$pair[0]], $runners[$pair[1]]],
            $assignment,
        );
    }

    /**
     * @param  array<int, array{team: Team, group: string}>  $winners
     * @param  array<int, array{team: Team, group: string}>  $runners
     * @param  array<int, array{0: int, 1: int}>  $picked
     * @return array<int, array{0: int, 1: int}>|null
     */
    private function matchWinners(array $winners, array $runners, array $picked): ?array
    {
        $index = count($picked);

        if ($index === count($winners)) {
            return $picked;
        }

        $used = array_column($picked, 1);

        foreach (array_keys($runners) as $runnerIndex) {
            if (in_array($runnerIndex, $used, true)) {
                continue;
            }

            $winner = $winners[$index];
            $runner = $runners[$runnerIndex];

            if ($winner['group'] === $runner['group']) {
                continue;
            }

            if ($winner['team']->country === $runner['team']->country) {
                continue;
            }

            $attempt = $this->matchWinners($winners, $runners, [...$picked, [$index, $runnerIndex]]);

            if ($attempt !== null) {
                return $attempt;
            }
        }

        return null;
    }

    private function createTie(string $stage, int $position, Team $legOneHome, Team $legOneAway): void
    {
        $tie = Tie::create([
            'stage' => $stage,
            'position' => $position,
            'home_team_id' => $legOneHome->id,
            'away_team_id' => $legOneAway->id,
        ]);

        $weeks = config("league.knockout_weeks.{$stage}");

        foreach ($weeks as $index => $week) {
            $leg = $index + 1;

            Game::create([
                'stage' => $stage,
                'tie_id' => $tie->id,
                'leg' => $leg,
                'week' => $week,
                'kickoff_at' => $this->kickoff($week, $stage === 'final' ? 0 : $position % 2),
                'home_team_id' => $leg === 1 ? $legOneHome->id : $legOneAway->id,
                'away_team_id' => $leg === 1 ? $legOneAway->id : $legOneHome->id,
            ]);
        }
    }

    private function kickoff(int $week, int $dayOffset): Carbon
    {
        $date = config('league.knockout_dates')[$week];

        return Carbon::parse("{$date} 21:00")->addDays($dayOffset);
    }

    private function playKnockoutWeek(int $week): void
    {
        $games = Game::with(['homeTeam.players', 'awayTeam.players'])
            ->where('stage', '!=', 'group')
            ->where('week', $week)
            ->where('is_played', false)
            ->get();

        foreach ($games as $game) {
            // Suspensions and injuries carry over; form shapes the odds.
            foreach ([$game->homeTeam, $game->awayTeam] as $team) {
                $unavailable = array_merge(
                    array_keys($this->suspensions->suspendedPlayers($team, $game)),
                    array_keys($this->injuries->injuredPlayers($team, $game)),
                );

                if ($unavailable !== []) {
                    $team->setRelation(
                        'players',
                        $team->players->reject(fn ($player) => in_array($player->id, $unavailable, true))->values(),
                    );
                }

                $this->form->adjust($team);
            }

            $result = $this->engine->play(
                $game->homeTeam,
                $game->awayTeam,
                neutral: $game->stage === 'final',
            );

            $this->persistResult($game, $result);
        }
    }

    private function resolveFinishedTies(): void
    {
        $ties = Tie::with(['games', 'homeTeam.players', 'awayTeam.players'])
            ->whereNull('winner_team_id')
            ->get()
            ->filter(fn (Tie $tie) => $tie->games->isNotEmpty() && $tie->games->every->is_played);

        foreach ($ties as $tie) {
            [$homeAggregate, $awayAggregate] = $this->aggregate($tie);

            if ($homeAggregate === $awayAggregate) {
                [$homeAggregate, $awayAggregate] = $this->playExtraTime($tie, $homeAggregate, $awayAggregate);
            }

            if ($homeAggregate === $awayAggregate) {
                [$homePens, $awayPens] = $this->shootout($tie->homeTeam, $tie->awayTeam);

                $tie->update([
                    'home_penalties' => $homePens,
                    'away_penalties' => $awayPens,
                    'winner_team_id' => $homePens > $awayPens ? $tie->home_team_id : $tie->away_team_id,
                ]);

                continue;
            }

            $tie->update([
                'winner_team_id' => $homeAggregate > $awayAggregate ? $tie->home_team_id : $tie->away_team_id,
            ]);
        }
    }

    /**
     * @param  Collection<int, Game>|null  $games  defaults to the tie's played games
     * @return array{0: int, 1: int} aggregate goals for the tie's home/away sides
     */
    private function aggregate(Tie $tie, ?Collection $games = null): array
    {
        $games ??= $tie->games->where('is_played', true);

        $home = 0;
        $away = 0;

        foreach ($games as $game) {
            if ($game->home_team_id === $tie->home_team_id) {
                $home += $game->home_goals;
                $away += $game->away_goals;
            } else {
                $home += $game->away_goals;
                $away += $game->home_goals;
            }
        }

        return [$home, $away];
    }

    /**
     * 30 extra minutes appended to the deciding leg, contested by the
     * players still on the pitch.
     *
     * @return array{0: int, 1: int} updated aggregates
     */
    private function playExtraTime(Tie $tie, int $homeAggregate, int $awayAggregate): array
    {
        $decider = $tie->games->sortByDesc('leg')->first();
        $decider->load('appearances.player');

        $onPitch = fn (int $teamId) => $decider->appearances
            ->where('team_id', $teamId)
            ->whereNull('went_off')
            ->pluck('player')
            ->filter()
            ->values();

        $extra = $this->engine instanceof MatchEngine
            ? $this->engine->extraTime(
                $decider->homeTeam,
                $decider->awayTeam,
                $onPitch($decider->home_team_id),
                $onPitch($decider->away_team_id),
            )
            : ['home_goals' => 0, 'away_goals' => 0, 'events' => []];

        if ($extra['events'] !== []) {
            $decider->events()->createMany($extra['events']);
        }

        $decider->update([
            'home_goals' => $decider->home_goals + $extra['home_goals'],
            'away_goals' => $decider->away_goals + $extra['away_goals'],
        ]);

        // Map the decider's sides back onto the tie's home/away.
        if ($decider->home_team_id === $tie->home_team_id) {
            return [$homeAggregate + $extra['home_goals'], $awayAggregate + $extra['away_goals']];
        }

        return [$homeAggregate + $extra['away_goals'], $awayAggregate + $extra['home_goals']];
    }

    /**
     * Penalty shoot-out: 5 rounds, then sudden death. Conversion odds
     * lean on the shooter's power and the opposing keeper.
     *
     * @return array{0: int, 1: int}
     */
    private function shootout(Team $home, Team $away): array
    {
        $chance = fn (Team $shooter, Team $keeper): float => max(0.55, min(0.9,
            0.76 + ($shooter->power - 78) / 400 - ($keeper->goalkeeper_factor - 80) / 500,
        ));

        $homeChance = $chance($home, $away);
        $awayChance = $chance($away, $home);

        $homeScore = 0;
        $awayScore = 0;

        for ($round = 1; $round <= 30; $round++) {
            $homeScore += $this->random->float() < $homeChance ? 1 : 0;
            $awayScore += $this->random->float() < $awayChance ? 1 : 0;

            $remaining = max(0, 5 - $round);

            // Decided early or past round five with a difference.
            if ($round >= 5 && $homeScore !== $awayScore) {
                break;
            }

            if ($round < 5 && abs($homeScore - $awayScore) > $remaining) {
                break;
            }
        }

        return [$homeScore, $awayScore];
    }

    private function seedNextStage(): void
    {
        $progression = ['r16' => 'qf', 'qf' => 'sf', 'sf' => 'final'];

        foreach ($progression as $from => $to) {
            $fromTies = Tie::where('stage', $from)->orderBy('position')->get();

            if ($fromTies->isEmpty() || Tie::where('stage', $to)->exists()) {
                continue;
            }

            if (! $fromTies->every(fn (Tie $tie) => $tie->winner_team_id !== null)) {
                continue;
            }

            $winners = $fromTies->map(fn (Tie $tie) => Team::find($tie->winner_team_id));

            // Open draw from the quarter-finals on (like UEFA).
            $shuffled = collect($this->shuffle($winners->all()));

            $position = 1;

            foreach ($shuffled->chunk(2) as $pair) {
                $pair = $pair->values();
                $this->createTie($to, $position++, $pair[0], $pair[1]);
            }
        }
    }

    /**
     * @param  Collection<int, Tie>|array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function shuffle(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = (int) floor($this->random->float() * ($i + 1));
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    private function groupStageOver(): bool
    {
        return Game::where('stage', 'group')->exists()
            && ! Game::where('stage', 'group')->where('is_played', false)->exists();
    }

    /**
     * @param  Collection<int, Tie>  $ties
     * @return array{action: string, label: string}|null
     */
    private function nextAction(bool $available, Collection $ties): ?array
    {
        if (! $available) {
            return null;
        }

        if ($ties->isEmpty()) {
            return ['action' => 'draw', 'label' => 'DRAW ROUND OF 16'];
        }

        $next = Game::where('stage', '!=', 'group')->where('is_played', false)->orderBy('week')->first();

        if ($next === null) {
            return ['action' => 'done', 'label' => 'TOURNAMENT DECIDED'];
        }

        $weeks = config("league.knockout_weeks.{$next->stage}");
        $label = self::STAGE_LABELS[$next->stage];

        return [
            'action' => 'play',
            'stage' => $next->stage,
            'leg' => count($weeks) > 1 ? $next->leg : null,
            'label' => count($weeks) > 1
                ? sprintf('PLAY %s — LEG %d', $label, $next->leg)
                : "PLAY THE {$label}",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tiePayload(Tie $tie): array
    {
        [$homeAggregate, $awayAggregate] = $this->aggregate($tie);

        return [
            'id' => $tie->id,
            'position' => $tie->position,
            'home_team' => $tie->homeTeam,
            'away_team' => $tie->awayTeam,
            'games' => $tie->games,
            'aggregate' => ['home' => $homeAggregate, 'away' => $awayAggregate],
            'penalties' => $tie->home_penalties !== null
                ? ['home' => $tie->home_penalties, 'away' => $tie->away_penalties]
                : null,
            'winner_team_id' => $tie->winner_team_id,
        ];
    }
}
