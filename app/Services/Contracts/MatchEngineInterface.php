<?php

namespace App\Services\Contracts;

use App\DataTransferObjects\MatchResult;
use App\Models\Game;
use App\Models\Team;

interface MatchEngineInterface
{
    /**
     * Play a full match: lineups, minute-by-minute events (goals,
     * cards, injuries, substitutions) and player ratings.
     *
     * @param  bool  $neutral  neutral venue (the final) — no home boost
     * @param  bool  $extraTime  append a 30-minute extra time (level knockout ties)
     */
    public function play(Team $home, Team $away, ?array $fixedScore = null, bool $neutral = false, bool $extraTime = false): MatchResult;

    /**
     * Rebuild a played match for a manually edited score: cards,
     * injuries, substitutions and appearances are preserved; only the
     * goals are regenerated and ratings recomputed.
     */
    public function rescore(Game $game, int $homeGoals, int $awayGoals): MatchResult;
}
