<?php

namespace Tests\Support;

use App\Models\Player;
use App\Models\Team;

/**
 * Creates a lightweight 32-team tournament (no EA squads) so feature
 * tests run fast while matching the real pot/country distribution.
 * Pass $withPlayers to attach a minimal 13-man squad to every club.
 */
trait SeedsTournament
{
    protected function seedTournament(bool $withPlayers = false): void
    {
        $countriesPerPot = [
            1 => ['ESP', 'ENG', 'GER', 'FRA', 'ENG', 'ESP', 'ENG', 'ITA'],
            2 => ['ENG', 'ESP', 'GER', 'ITA', 'GER', 'POR', 'POR', 'POR'],
            3 => ['ENG', 'ESP', 'GER', 'FRA', 'FRA', 'SCO', 'BEL', 'GRE'],
            4 => ['TUR', 'TUR', 'SCO', 'BEL', 'DEN', 'CZE', 'NOR', 'AZE'],
        ];

        $index = 0;

        foreach ($countriesPerPot as $pot => $countries) {
            foreach ($countries as $country) {
                $team = Team::factory()->create([
                    'name' => "Club {$index}",
                    'code' => 'C'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                    'country' => $country,
                    'pot' => $pot,
                ]);

                if ($withPlayers) {
                    $this->seedSquad($team);
                }

                $index++;
            }
        }
    }

    private function seedSquad(Team $team): void
    {
        $blueprint = array_merge(
            [['GK', 'goalkeeper'], ['GK', 'goalkeeper']],
            array_fill(0, 4, ['CB', 'defense']),
            array_fill(0, 4, ['CM', 'midfielder']),
            array_fill(0, 3, ['ST', 'attack']),
        );

        foreach ($blueprint as [$position, $type]) {
            Player::factory()->for($team)->create([
                'position' => $position,
                'position_type' => $type,
            ]);
        }
    }
}
