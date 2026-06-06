<?php

namespace App\Services;

use App\Services\Contracts\MatchSchedulerInterface;
use App\Services\Contracts\RandomGeneratorInterface;
use RuntimeException;

/**
 * Tuesday/Wednesday split (FAQ #4).
 *
 * Associations with several clubs get them paired; each pair's groups
 * must play on different days so the association's matches split
 * between Tuesday and Wednesday. A backtracking 2-colouring finds a
 * base assignment (four groups per day); odd matchdays use it as-is,
 * even matchdays invert it, so every club plays both days.
 */
class MatchScheduler implements MatchSchedulerInterface
{
    private const TUESDAY = 0;

    private const WEDNESDAY = 1;

    public function __construct(
        private readonly RandomGeneratorInterface $random,
    ) {
    }

    public function schedule(array $groupCountries, int $weeks): array
    {
        $groups = array_keys($groupCountries);
        $pairs = $this->associationPairs($groupCountries);

        // Shuffle the solve order so the split varies draw to draw
        // instead of always being "first four Tuesday".
        $base = $this->solve($this->shuffle($groups), $pairs);

        if ($base === null) {
            // The same-association pairing is a scheduling preference;
            // fall back to a plain 4/4 split rather than failing the draw.
            $base = $this->solve($this->shuffle($groups), []);

            if ($base === null) {
                throw new RuntimeException('Could not split the groups across Tuesday and Wednesday.');
            }
        }

        // Random global flip: matchday 1 isn't always the same half.
        if ($this->random->float() < 0.5) {
            $base = array_map(fn (int $day): int => 1 - $day, $base);
        }

        $result = [];

        foreach ($groups as $group) {
            foreach (range(1, $weeks) as $week) {
                // Alternate so every club gets both Tuesday and Wednesday nights.
                $result[$group][$week] = $week % 2 === 1
                    ? $base[$group]
                    : 1 - $base[$group];
            }
        }

        return $result;
    }

    /**
     * Pair up the groups hosting clubs of the same association:
     * [g1, g2], [g3, g4], … per country (the odd one out is free).
     *
     * @param  array<string, array<int, string>>  $groupCountries
     * @return array<int, array{0: string, 1: string}>
     */
    private function associationPairs(array $groupCountries): array
    {
        $byCountry = [];

        foreach ($groupCountries as $group => $countries) {
            foreach ($countries as $country) {
                $byCountry[$country][] = $group;
            }
        }

        $pairs = [];

        foreach ($byCountry as $groups) {
            for ($i = 0; $i + 1 < count($groups); $i += 2) {
                $pairs[] = [$groups[$i], $groups[$i + 1]];
            }
        }

        return $pairs;
    }

    /**
     * Backtracking: assign 0/1 to every group, max half per day, paired
     * groups on different days.
     *
     * @param  array<int, string>  $groups
     * @param  array<int, array{0: string, 1: string}>  $pairs
     * @return array<string, int>|null
     */
    private function solve(array $groups, array $pairs, array $assigned = []): ?array
    {
        if (count($assigned) === count($groups)) {
            return $assigned;
        }

        $group = $groups[count($assigned)];
        $capacity = intdiv(count($groups), 2);

        foreach ([self::TUESDAY, self::WEDNESDAY] as $day) {
            if (count(array_keys($assigned, $day, true)) >= $capacity) {
                continue;
            }

            if ($this->violatesPair($pairs, $assigned, $group, $day)) {
                continue;
            }

            $attempt = $this->solve($groups, $pairs, $assigned + [$group => $day]);

            if ($attempt !== null) {
                return $attempt;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $pairs
     * @param  array<string, int>  $assigned
     */
    /**
     * @param  array<int, string>  $items
     * @return array<int, string>
     */
    private function shuffle(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = (int) floor($this->random->float() * ($i + 1));
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    private function violatesPair(array $pairs, array $assigned, string $group, int $day): bool
    {
        foreach ($pairs as [$a, $b]) {
            $other = match ($group) {
                $a => $b,
                $b => $a,
                default => null,
            };

            if ($other !== null && ($assigned[$other] ?? null) === $day) {
                return true;
            }
        }

        return false;
    }
}
