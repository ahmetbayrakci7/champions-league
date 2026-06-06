<?php

namespace App\Services;

use App\Models\Appearance;
use App\Models\Game;
use App\Models\Team;
use App\Services\Contracts\TeamFormServiceInterface;

/**
 * Momentum: the average player rating across the team's last few
 * matches nudges its effective power up or down, so the win odds
 * (and results) react to how the squad is actually performing.
 */
class TeamFormService implements TeamFormServiceInterface
{
    public function factor(Team $team): float
    {
        $config = config('league.form');

        $recentGameIds = Game::where('is_played', true)
            ->where(fn ($query) => $query
                ->where('home_team_id', $team->id)
                ->orWhere('away_team_id', $team->id))
            ->orderByDesc('week')
            ->limit((int) $config['window'])
            ->pluck('id');

        if ($recentGameIds->isEmpty()) {
            return 1.0;
        }

        $average = Appearance::whereIn('game_id', $recentGameIds)
            ->where('team_id', $team->id)
            ->whereNotNull('rating')
            ->avg('rating');

        if ($average === null) {
            return 1.0;
        }

        $factor = 1 + ((float) $average - (float) $config['baseline']) * (float) $config['sensitivity'];

        return max((float) $config['min'], min((float) $config['max'], $factor));
    }

    public function adjust(Team $team): void
    {
        $team->power = max(1, min(100, (int) round($team->power * $this->factor($team))));
    }
}
