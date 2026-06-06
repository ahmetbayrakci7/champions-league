<?php

namespace App\Services\Contracts;

use App\DataTransferObjects\MatchResult;
use App\Models\Team;

interface MatchEngineInterface
{
    /**
     * Play a full match: lineups, minute-by-minute events (goals,
     * cards, injuries, substitutions) and player ratings.
     *
     * @param  array{home: int, away: int}|null  $fixedScore  force an exact score (manual edits)
     * @param  bool  $neutral  neutral venue (the final) — no home boost
     * @param  bool  $extraTime  append a 30-minute extra time (level knockout ties)
     */
    public function play(Team $home, Team $away, ?array $fixedScore = null, bool $neutral = false, bool $extraTime = false): MatchResult;
}
