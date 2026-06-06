<?php

namespace App\Services;

use App\Models\Game;
use App\Models\MatchEvent;
use App\Services\Contracts\StatsServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Leaderboards computed straight from the match data (events,
 * appearances, games) — never stored, so edits stay consistent.
 */
class StatsService implements StatsServiceInterface
{
    private const TOP = 5;

    private const MIN_APPS_FOR_RATING = 3;

    public function overview(): array
    {
        $played = Game::where('is_played', true)->count();

        if ($played === 0) {
            return ['played_games' => 0, 'players' => null, 'teams' => null];
        }

        return [
            'played_games' => $played,
            'players' => [
                'scorers' => $this->playerEventBoard([MatchEvent::TYPE_GOAL], 'player_id'),
                'assists' => $this->playerEventBoard([MatchEvent::TYPE_GOAL], 'related_player_id'),
                'contributions' => $this->contributionBoard(),
                'ratings' => $this->ratingBoard(),
                'cards' => $this->playerEventBoard([MatchEvent::TYPE_YELLOW, MatchEvent::TYPE_RED], 'player_id'),
                'reds' => $this->playerEventBoard([MatchEvent::TYPE_RED], 'player_id'),
            ],
            'teams' => [
                'attack' => $this->teamGoalBoard(scored: true),
                'defence' => $this->teamGoalBoard(scored: false),
                'cards' => $this->teamCardBoard(),
                'clean_sheets' => $this->cleanSheetBoard(),
                'biggest_win' => $this->biggestWin(),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $types
     */
    private function playerEventBoard(array $types, string $column): Collection
    {
        return MatchEvent::query()
            ->whereIn('type', $types)
            ->whereNotNull($column)
            ->select($column.' as player_id', DB::raw('count(*) as total'))
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit(self::TOP)
            ->with('player.team:id,name,code,color,logo_url')
            ->get()
            ->map(fn ($row) => $this->playerRow($row->player, (int) $row->total));
    }

    private function contributionBoard(): Collection
    {
        return MatchEvent::query()
            ->where('type', MatchEvent::TYPE_GOAL)
            ->selectRaw('player_id as pid, count(*) as goals, 0 as assists')
            ->whereNotNull('player_id')
            ->groupBy('player_id')
            ->unionAll(
                MatchEvent::query()
                    ->where('type', MatchEvent::TYPE_GOAL)
                    ->selectRaw('related_player_id as pid, 0 as goals, count(*) as assists')
                    ->whereNotNull('related_player_id')
                    ->groupBy('related_player_id'),
            )
            ->get()
            ->groupBy('pid')
            ->map(fn ($rows, $pid) => [
                'player_id' => (int) $pid,
                'goals' => (int) $rows->sum('goals'),
                'assists' => (int) $rows->sum('assists'),
            ])
            ->sortByDesc(fn ($row) => $row['goals'] + $row['assists'])
            ->take(self::TOP)
            ->values()
            ->map(function (array $row) {
                $player = \App\Models\Player::with('team:id,name,code,color,logo_url')->find($row['player_id']);

                return $this->playerRow($player, $row['goals'] + $row['assists'])
                    + ['goals' => $row['goals'], 'assists' => $row['assists']];
            });
    }

    private function ratingBoard(): Collection
    {
        return \App\Models\Appearance::query()
            ->select('player_id', DB::raw('round(avg(rating), 2) as avg_rating'), DB::raw('count(*) as apps'))
            ->whereNotNull('rating')
            ->groupBy('player_id')
            ->having('apps', '>=', self::MIN_APPS_FOR_RATING)
            ->orderByDesc('avg_rating')
            ->limit(self::TOP)
            ->with('player.team:id,name,code,color,logo_url')
            ->get()
            ->map(fn ($row) => $this->playerRow($row->player, (float) $row->avg_rating) + ['apps' => (int) $row->apps]);
    }

    private function teamGoalBoard(bool $scored): Collection
    {
        $rows = Game::where('is_played', true)
            ->get(['home_team_id', 'away_team_id', 'home_goals', 'away_goals'])
            ->flatMap(fn (Game $game) => [
                ['team_id' => $game->home_team_id, 'value' => $scored ? $game->home_goals : $game->away_goals],
                ['team_id' => $game->away_team_id, 'value' => $scored ? $game->away_goals : $game->home_goals],
            ])
            ->groupBy('team_id')
            ->map(fn ($items) => (int) $items->sum('value'));

        $sorted = $scored ? $rows->sortDesc() : $rows->sort();

        return $this->teamBoard($sorted->take(self::TOP));
    }

    private function teamCardBoard(): Collection
    {
        $rows = MatchEvent::query()
            ->whereIn('type', [MatchEvent::TYPE_YELLOW, MatchEvent::TYPE_RED])
            ->select('team_id', DB::raw('count(*) as total'))
            ->groupBy('team_id')
            ->orderByDesc('total')
            ->limit(self::TOP)
            ->pluck('total', 'team_id');

        return $this->teamBoard($rows);
    }

    private function cleanSheetBoard(): Collection
    {
        $rows = Game::where('is_played', true)
            ->get(['home_team_id', 'away_team_id', 'home_goals', 'away_goals'])
            ->flatMap(fn (Game $game) => [
                ['team_id' => $game->home_team_id, 'clean' => $game->away_goals === 0 ? 1 : 0],
                ['team_id' => $game->away_team_id, 'clean' => $game->home_goals === 0 ? 1 : 0],
            ])
            ->groupBy('team_id')
            ->map(fn ($items) => (int) $items->sum('clean'))
            ->sortDesc()
            ->take(self::TOP);

        return $this->teamBoard($rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function biggestWin(): ?array
    {
        $game = Game::where('is_played', true)
            ->with(['homeTeam:id,name,code,color,logo_url', 'awayTeam:id,name,code,color,logo_url'])
            ->get()
            ->sortByDesc(fn (Game $game) => abs($game->home_goals - $game->away_goals))
            ->first();

        if ($game === null || $game->home_goals === $game->away_goals) {
            return null;
        }

        return [
            'home_team' => $game->homeTeam,
            'away_team' => $game->awayTeam,
            'home_goals' => $game->home_goals,
            'away_goals' => $game->away_goals,
            'margin' => abs($game->home_goals - $game->away_goals),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, int>  $totals  team_id => value
     */
    private function teamBoard(Collection $totals): Collection
    {
        $teams = \App\Models\Team::whereIn('id', $totals->keys())
            ->get(['id', 'name', 'code', 'color', 'logo_url'])
            ->keyBy('id');

        return $totals
            ->map(fn ($value, $teamId) => [
                'team' => $teams[$teamId] ?? null,
                'value' => $value,
            ])
            ->filter(fn ($row) => $row['team'] !== null)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function playerRow(?\App\Models\Player $player, int|float $value): array
    {
        return [
            'player_id' => $player?->id,
            'name' => $player?->name ?? 'Unknown',
            'position' => $player?->position,
            'avatar_url' => $player?->avatar_url,
            'team' => $player?->team,
            'value' => $value,
        ];
    }
}
