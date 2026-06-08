<?php

namespace App\Services\Support;

use App\DataTransferObjects\MatchResult;
use App\Models\Appearance;
use App\Models\Game;
use App\Models\Injury;
use App\Models\MatchEvent;

trait PersistsMatchResults
{
    /**
     * Store a simulated match: score, event timeline, appearances and
     * any injuries picked up. Replaces any previous simulation of the
     * same game (score edits).
     */
    private function persistResult(Game $game, MatchResult $result): void
    {
        $game->events()->delete();
        $game->appearances()->delete();
        Injury::where('game_id', $game->id)->delete();

        $game->update([
            'home_goals' => $result->homeGoals,
            'away_goals' => $result->awayGoals,
            'is_played' => true,
        ]);

        $now = now();

        MatchEvent::insert(array_map(
            function (array $event) use ($game, $now): array {
                unset($event['injury_matches']);

                // Bulk insert bypasses casts: encode the params manually.
                if (isset($event['params'])) {
                    $event['params'] = json_encode($event['params'], JSON_UNESCAPED_UNICODE);
                }

                return $event + ['game_id' => $game->id, 'created_at' => $now, 'updated_at' => $now];
            },
            $result->events,
        ));

        foreach ($result->events as $event) {
            if (($event['injury_matches'] ?? null) !== null && $event['player_id'] !== null) {
                Injury::create([
                    'game_id' => $game->id,
                    'team_id' => $event['team_id'],
                    'player_id' => $event['player_id'],
                    'matches' => $event['injury_matches'],
                ]);
            }
        }

        Appearance::insert(array_map(
            fn (array $appearance): array => $appearance + ['game_id' => $game->id, 'created_at' => $now, 'updated_at' => $now],
            $result->appearances,
        ));
    }

    /**
     * Persist a manually edited result: replaces the score, events and
     * appearance ratings but LEAVES the injuries table untouched (an
     * edit only changes the scoreline, never the cards/injuries).
     */
    private function persistEditedResult(Game $game, MatchResult $result): void
    {
        $game->events()->delete();
        $game->appearances()->delete();

        $game->update([
            'home_goals' => $result->homeGoals,
            'away_goals' => $result->awayGoals,
            'is_played' => true,
        ]);

        $now = now();

        MatchEvent::insert(array_map(
            function (array $event) use ($game, $now): array {
                if (isset($event['params'])) {
                    $event['params'] = json_encode($event['params'], JSON_UNESCAPED_UNICODE);
                }

                return $event + ['game_id' => $game->id, 'created_at' => $now, 'updated_at' => $now];
            },
            $result->events,
        ));

        Appearance::insert(array_map(
            fn (array $appearance): array => $appearance + ['game_id' => $game->id, 'created_at' => $now, 'updated_at' => $now],
            $result->appearances,
        ));
    }
}
