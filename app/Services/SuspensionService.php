<?php

namespace App\Services;

use App\Models\Game;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Services\Contracts\SuspensionServiceInterface;

/**
 * One-match bans (UEFA style): a straight red or second yellow, and
 * every 3rd accumulated yellow card, rule the player out of his
 * team's next fixture — including across the group/knockout border.
 *
 * Yellow-card amnesty: ALL bookings are wiped once the quarter-finals
 * are complete — from the semi-finals on nobody can miss a match
 * through yellow accumulation. Red cards are never forgiven: a sending
 * off in the SF second leg still costs the final.
 */
class SuspensionService implements SuspensionServiceInterface
{
    public function suspendedPlayers(Team $team, Game $game): array
    {
        $previous = Game::where('is_played', true)
            ->where('week', '<', $game->week)
            ->where(fn ($query) => $query
                ->where('home_team_id', $team->id)
                ->orWhere('away_team_id', $team->id))
            ->orderByDesc('week')
            ->first();

        if ($previous === null) {
            return [];
        }

        // The ban only covers the team's NEXT fixture: if another game
        // sits between the offence and this one, it has been served.
        $intermediate = Game::where('week', '>', $previous->week)
            ->where('week', '<', $game->week)
            ->where(fn ($query) => $query
                ->where('home_team_id', $team->id)
                ->orWhere('away_team_id', $team->id))
            ->exists();

        if ($intermediate) {
            return [];
        }

        $suspended = [];

        // Red card in the previous match (direct or second yellow).
        $reds = MatchEvent::where('game_id', $previous->id)
            ->where('team_id', $team->id)
            ->where('type', MatchEvent::TYPE_RED)
            ->pluck('player_id');

        foreach ($reds as $playerId) {
            $suspended[$playerId] = 'red card';
        }

        // A yellow in the previous match that completed an accumulation
        // cycle (3rd, 6th, 9th… booking of the campaign).
        $bookedLastMatch = MatchEvent::where('game_id', $previous->id)
            ->where('team_id', $team->id)
            ->where('type', MatchEvent::TYPE_YELLOW)
            ->pluck('player_id');

        if ($bookedLastMatch->isNotEmpty()) {
            $threshold = (int) config('league.suspension.yellow_threshold');

            // Yellow amnesty: bookings are wiped once the quarter-finals
            // are complete. For any match after the reset point only
            // post-QF yellows count — so a 3rd yellow picked up in the
            // QF second leg no longer bans anyone for the semi-final.
            $resetWeek = max(config('league.knockout_weeks.qf', [0]));

            $teamGameIds = Game::where('is_played', true)
                ->where('week', '<=', $previous->week)
                ->when(
                    $resetWeek > 0 && $game->week > $resetWeek,
                    fn ($query) => $query->where('week', '>', $resetWeek),
                )
                ->where(fn ($query) => $query
                    ->where('home_team_id', $team->id)
                    ->orWhere('away_team_id', $team->id))
                ->pluck('id');

            $totals = MatchEvent::whereIn('game_id', $teamGameIds)
                ->where('team_id', $team->id)
                ->where('type', MatchEvent::TYPE_YELLOW)
                ->whereIn('player_id', $bookedLastMatch)
                ->selectRaw('player_id, count(*) as total')
                ->groupBy('player_id')
                ->pluck('total', 'player_id');

            foreach ($totals as $playerId => $total) {
                if ($total >= $threshold && $total % $threshold === 0) {
                    $suspended[$playerId] ??= 'yellow card accumulation';
                }
            }
        }

        return $suspended;
    }
}
