<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Injury;
use App\Models\Team;
use App\Services\Contracts\InjuryServiceInterface;

/**
 * Injury lay-offs: a knock picked up in match P rules the player out
 * of his team's next N fixtures (week order — knockout included).
 */
class InjuryService implements InjuryServiceInterface
{
    public function injuredPlayers(Team $team, Game $game): array
    {
        $injuries = Injury::where('team_id', $team->id)
            ->with('game:id,week')
            ->get();

        if ($injuries->isEmpty()) {
            return [];
        }

        $out = [];

        foreach ($injuries as $injury) {
            $injuryWeek = $injury->game?->week;

            if ($injuryWeek === null || $game->week <= $injuryWeek) {
                continue;
            }

            // How many of the team's fixtures lie between the injury
            // and this game (inclusive)?
            $fixturesSince = Game::where('week', '>', $injuryWeek)
                ->where('week', '<=', $game->week)
                ->where(fn ($query) => $query
                    ->where('home_team_id', $team->id)
                    ->orWhere('away_team_id', $team->id))
                ->count();

            if ($fixturesSince <= $injury->matches) {
                $remaining = $injury->matches - $fixturesSince + 1;
                $out[$injury->player_id] = max($out[$injury->player_id] ?? 0, $remaining);
            }
        }

        return $out;
    }
}
