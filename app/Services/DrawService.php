<?php

namespace App\Services;

use App\Services\Contracts\DrawServiceInterface;
use App\Services\Contracts\RandomGeneratorInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

/**
 * UEFA-style group stage draw (FAQ #4).
 *
 * Teams are shuffled within their pots, then placed pot by pot via
 * backtracking so that every group receives exactly one club per pot
 * and never two clubs from the same association.
 */
class DrawService implements DrawServiceInterface
{
    public function __construct(
        private readonly RandomGeneratorInterface $random,
    ) {
    }

    public function draw(Collection $teams): array
    {
        $groupNames = config('league.group_names');
        $groupCount = count($groupNames);

        $pots = $teams->groupBy('pot')->sortKeys();

        foreach ($pots as $pot => $potTeams) {
            if ($potTeams->count() !== $groupCount) {
                throw new InvalidArgumentException(
                    "Pot {$pot} holds {$potTeams->count()} teams, expected {$groupCount}.",
                );
            }
        }

        // Draw order: every pot shuffled, pot 1 placed first.
        $ordered = $pots
            ->map(fn (Collection $potTeams) => $this->shuffle($potTeams->values()))
            ->flatten(1)
            ->values();

        /** @var array<int, array<int, \App\Models\Team>> $slots groupIndex => teams */
        $slots = array_fill(0, $groupCount, []);

        if (! $this->place($ordered, 0, $slots, $groupCount)) {
            throw new RuntimeException('No valid group draw exists for the given teams.');
        }

        $result = [];

        foreach ($groupNames as $index => $name) {
            $result[$name] = array_map(fn ($team) => $team->id, $slots[$index]);
        }

        return $result;
    }

    /**
     * Backtracking placement: team $index tries every group that still
     * misses a club of its pot and has no club of its association.
     *
     * @param  Collection<int, \App\Models\Team>  $ordered
     * @param  array<int, array<int, \App\Models\Team>>  $slots
     */
    private function place(Collection $ordered, int $index, array &$slots, int $groupCount): bool
    {
        if ($index === $ordered->count()) {
            return true;
        }

        $team = $ordered[$index];
        $potIndex = $team->pot - 1;

        foreach (range(0, $groupCount - 1) as $group) {
            if (count($slots[$group]) !== $potIndex) {
                continue; // group already has a club from this pot (or pots were skipped)
            }

            $sameCountry = array_filter(
                $slots[$group],
                fn ($placed) => $placed->country === $team->country,
            );

            if ($sameCountry !== []) {
                continue;
            }

            $slots[$group][] = $team;

            if ($this->place($ordered, $index + 1, $slots, $groupCount)) {
                return true;
            }

            array_pop($slots[$group]);
        }

        return false;
    }

    /**
     * Fisher-Yates with the injected RNG so draws are testable.
     *
     * @param  Collection<int, \App\Models\Team>  $items
     * @return Collection<int, \App\Models\Team>
     */
    private function shuffle(Collection $items): Collection
    {
        $array = $items->all();

        for ($i = count($array) - 1; $i > 0; $i--) {
            $j = (int) floor($this->random->float() * ($i + 1));
            [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
        }

        return collect($array);
    }
}
