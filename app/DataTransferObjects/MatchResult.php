<?php

namespace App\DataTransferObjects;

/**
 * Full outcome of a simulated match: score, minute-by-minute events
 * and per-player appearance records with performance ratings.
 */
final class MatchResult
{
    /**
     * @param  array<int, array{minute: int, type: string, team_id: int, player_id: int|null, related_player_id: int|null, commentary: string}>  $events
     * @param  array<int, array{team_id: int, player_id: int, is_starting: bool, came_on: int|null, went_off: int|null, rating: float}>  $appearances
     */
    public function __construct(
        public readonly int $homeGoals,
        public readonly int $awayGoals,
        public readonly array $events,
        public readonly array $appearances,
    ) {
    }
}
