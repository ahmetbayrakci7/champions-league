<?php

namespace App\Services;

use App\Models\Team;
use App\Services\Contracts\LineupSelectorInterface;
use Illuminate\Support\Collection;

/**
 * Best-available XI: the strongest goalkeeper plus a balanced outfield
 * (at least 3 defenders, 3 midfielders, 1 forward — remaining slots go
 * to the highest-rated players left). Next 7 players make the bench.
 */
class LineupSelector implements LineupSelectorInterface
{
    private const BENCH_SIZE = 7;

    public function select(Team $team): array
    {
        $players = $team->players->sortByDesc('overall')->values();

        $keepers = $players->where('position_type', 'goalkeeper')->values();
        $outfield = $players->where('position_type', '!=', 'goalkeeper')->values();

        $starters = collect();

        if ($keepers->isNotEmpty()) {
            $starters->push($keepers->first());
        }

        // Positional minimums keep the XI realistic.
        foreach (['defense' => 3, 'midfielder' => 3, 'attack' => 1] as $type => $minimum) {
            $starters = $starters->merge(
                $outfield->where('position_type', $type)->take($minimum),
            );
        }

        $remaining = $outfield
            ->reject(fn ($player) => $starters->contains('id', $player->id))
            ->values();

        $starters = $starters
            ->merge($remaining->take(11 - $starters->count()))
            ->take(11)
            ->values();

        $bench = $players
            ->reject(fn ($player) => $starters->contains('id', $player->id))
            ->take(self::BENCH_SIZE)
            ->values();

        return ['starters' => $starters, 'bench' => $bench];
    }
}
