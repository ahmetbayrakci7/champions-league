<?php

namespace App\Services;

use App\Services\Contracts\FixtureGeneratorInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Double round-robin fixture using the circle (Berger) method.
 * For 4 teams this yields 6 weeks with 2 matches per week.
 */
class FixtureGenerator implements FixtureGeneratorInterface
{
    public function generate(Collection $teams): array
    {
        $ids = $teams->pluck('id')->values()->all();
        $count = count($ids);

        if ($count < 2 || $count % 2 !== 0) {
            throw new InvalidArgumentException('Fixture generation requires an even number of at least 2 teams.');
        }

        $rounds = $count - 1;
        $half = intdiv($count, 2);

        // Circle method: fix the first team, rotate the rest clockwise each round.
        $rotation = $ids;
        $fixed = array_shift($rotation);

        $fixture = [];

        for ($round = 0; $round < $rounds; $round++) {
            $left = array_merge([$fixed], array_slice($rotation, 0, $half - 1));
            $right = array_reverse(array_slice($rotation, $half - 1));

            foreach ($left as $i => $homeId) {
                $awayId = $right[$i];

                // Alternate the fixed team's venue so home games spread fairly.
                if ($i === 0 && $round % 2 === 1) {
                    [$homeId, $awayId] = [$awayId, $homeId];
                }

                $fixture[] = [
                    'week' => $round + 1,
                    'home_team_id' => $homeId,
                    'away_team_id' => $awayId,
                ];
            }

            // Rotate: last element moves to the front.
            array_unshift($rotation, array_pop($rotation));
        }

        // Second leg: mirror every first-leg match with venues swapped.
        foreach (array_slice($fixture, 0) as $match) {
            $fixture[] = [
                'week' => $match['week'] + $rounds,
                'home_team_id' => $match['away_team_id'],
                'away_team_id' => $match['home_team_id'],
            ];
        }

        return $fixture;
    }
}
