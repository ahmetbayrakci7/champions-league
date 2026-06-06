<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Group;
use App\Models\Team;
use App\Services\Contracts\ChampionshipPredictorInterface;
use App\Services\Contracts\DrawServiceInterface;
use App\Services\Contracts\FixtureGeneratorInterface;
use App\Services\Contracts\KnockoutServiceInterface;
use App\Services\Contracts\LeagueServiceInterface;
use App\Services\Contracts\MatchEngineInterface;
use App\Services\Contracts\MatchSchedulerInterface;
use App\Services\Contracts\RandomGeneratorInterface;
use App\Services\Contracts\StandingsCalculatorInterface;
use App\Services\Contracts\InjuryServiceInterface;
use App\Services\Contracts\SuspensionServiceInterface;
use App\Services\Contracts\TeamFormServiceInterface;
use App\Models\Tie;
use App\Services\Support\PersistsMatchResults;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeagueService implements LeagueServiceInterface
{
    use PersistsMatchResults;

    private const TEAM_FIELDS = ['id', 'name', 'code', 'color', 'country', 'pot', 'logo_url', 'power', 'group_id'];

    public function __construct(
        private readonly DrawServiceInterface $drawService,
        private readonly FixtureGeneratorInterface $fixtureGenerator,
        private readonly MatchSchedulerInterface $scheduler,
        private readonly MatchEngineInterface $engine,
        private readonly StandingsCalculatorInterface $standings,
        private readonly ChampionshipPredictorInterface $predictor,
        private readonly RandomGeneratorInterface $random,
        private readonly KnockoutServiceInterface $knockout,
        private readonly SuspensionServiceInterface $suspensions,
        private readonly InjuryServiceInterface $injuries,
        private readonly TeamFormServiceInterface $form,
    ) {
    }

    public function state(): array
    {
        $teams = Team::orderBy('pot')->orderBy('name')->get();
        $drawn = $teams->whereNotNull('group_id')->isNotEmpty();

        if (! $drawn) {
            return [
                'drawn' => false,
                'pots' => $teams->groupBy('pot')->sortKeys()
                    ->map(fn ($potTeams, $pot) => [
                        'pot' => $pot,
                        'teams' => $potTeams->map(fn (Team $team) => $team->only(self::TEAM_FIELDS))->values(),
                    ])->values(),
                'groups' => [],
                'current_week' => 0,
                'total_weeks' => 0,
                'season_over' => false,
                'knockout' => null,
            ];
        }

        $games = Game::with([
            'homeTeam:id,name,code,color,logo_url',
            'awayTeam:id,name,code,color,logo_url',
        ])->where('stage', 'group')
            ->orderBy('week')->orderBy('kickoff_at')->orderBy('id')->get();

        $playedWeeks = (int) ($games->where('is_played', true)->max('week') ?? 0);
        $totalWeeks = (int) ($games->max('week') ?? 0);
        $predictionsOn = $playedWeeks >= (int) config('league.prediction_start_week');

        $groups = Group::orderBy('name')->get()->map(function (Group $group) use ($teams, $games, $predictionsOn) {
            $groupTeams = $teams->where('group_id', $group->id)->values();
            $groupGames = $games->where('group_id', $group->id)->values();
            $played = $groupGames->where('is_played', true)->values();
            $remaining = $groupGames->where('is_played', false)->values();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'standings' => array_map(
                    fn ($standing) => $standing->toArray(),
                    $this->standings->calculate($groupTeams, $played),
                ),
                'weeks' => $groupGames->groupBy('week')
                    ->map(fn ($weekGames, $week) => ['week' => $week, 'games' => $weekGames->values()])
                    ->values(),
                'predictions' => $predictionsOn
                    ? $this->predictor->predict($groupTeams, $played, $remaining)
                    : null,
            ];
        });

        return [
            'drawn' => true,
            'pots' => [],
            'groups' => $groups,
            'current_week' => $playedWeeks,
            'total_weeks' => $totalWeeks,
            'season_over' => $games->isNotEmpty() && ! $games->contains('is_played', false),
            'knockout' => $this->knockout->state(),
        ];
    }

    public function drawGroups(): void
    {
        $teams = Team::all();

        DB::transaction(function () use ($teams): void {
            Game::query()->delete();

            $assignment = $this->drawService->draw($teams);

            // Tuesday/Wednesday split per matchday (FAQ #4).
            $groupCountries = collect($assignment)
                ->map(fn (array $teamIds) => $teams->whereIn('id', $teamIds)->pluck('country')->all())
                ->all();

            $teamsPerGroup = count($assignment[array_key_first($assignment)]);
            $weeksPerGroup = ($teamsPerGroup - 1) * 2; // double round-robin

            $days = $this->scheduler->schedule($groupCountries, $weeksPerGroup);

            foreach ($assignment as $groupName => $teamIds) {
                $group = Group::firstOrCreate(['name' => $groupName]);

                Team::whereIn('id', $teamIds)->update(['group_id' => $group->id]);

                $groupTeams = $teams->whereIn('id', $teamIds)->values();

                $slotPerWeek = [];

                foreach ($this->fixtureGenerator->generate($groupTeams) as $match) {
                    $week = $match['week'];
                    $slot = $slotPerWeek[$week] ?? 0;
                    $slotPerWeek[$week] = $slot + 1;

                    Game::create($match + [
                        'group_id' => $group->id,
                        'kickoff_at' => $this->kickoff($week, $days[$groupName][$week], $slot),
                    ]);
                }
            }
        });
    }

    /**
     * Matchday Tuesday (config date) or Wednesday (+1 day). The group's
     * first game draws a random early-evening slot, the second a random
     * late-evening slot — varied, but always at sensible match hours.
     */
    private function kickoff(int $week, int $dayOffset, int $slot): Carbon
    {
        $date = config('league.matchday_dates')[$week - 1];
        $pool = config('league.kickoff_slots')[$slot === 0 ? 'early' : 'late'];
        $time = $pool[(int) floor($this->random->float() * count($pool))];

        return Carbon::parse("{$date} {$time}")->addDays($dayOffset);
    }

    public function playNextWeek(): ?int
    {
        $nextWeek = Game::where('stage', 'group')->where('is_played', false)->min('week');

        if ($nextWeek === null) {
            return null;
        }

        $this->playWeek((int) $nextWeek);

        return (int) $nextWeek;
    }

    public function playAll(): void
    {
        while ($this->playNextWeek() !== null) {
            // keep playing until the fixture is exhausted
        }
    }

    public function reset(): void
    {
        DB::transaction(function (): void {
            Game::query()->delete();
            Tie::query()->delete();
            Team::query()->update(['group_id' => null]);
        });
    }

    /**
     * Manual score edits replay the whole match with the new score
     * fixed, so the timeline, lineups and ratings stay consistent.
     */
    public function updateGame(Game $game, int $homeGoals, int $awayGoals): Game
    {
        $game->load(['homeTeam.players', 'awayTeam.players']);

        $this->prepareSides($game);

        $result = $this->engine->play(
            $game->homeTeam,
            $game->awayTeam,
            ['home' => $homeGoals, 'away' => $awayGoals],
        );

        DB::transaction(fn () => $this->persistResult($game, $result));

        return $game->refresh();
    }

    private function playWeek(int $week): void
    {
        /** @var EloquentCollection<int, Game> $games */
        $games = Game::with(['homeTeam.players', 'awayTeam.players'])
            ->where('stage', 'group')
            ->where('week', $week)
            ->where('is_played', false)
            ->get();

        DB::transaction(function () use ($games): void {
            foreach ($games as $game) {
                $this->prepareSides($game);

                $result = $this->engine->play($game->homeTeam, $game->awayTeam);

                $this->persistResult($game, $result);
            }
        });
    }

    /**
     * Pre-match adjustments: drop suspended and injured players from
     * the squads and fold recent form into the effective power
     * (in memory only — never persisted).
     */
    private function prepareSides(Game $game): void
    {
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
    }
}
