<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Services\SquadImporter;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Seed the 32 Champions League clubs (config/league.php) and import
     * each club's current squad from the bundled EA ratings snapshot.
     */
    public function run(SquadImporter $importer): void
    {
        foreach (config('league.clubs') as $club) {
            $team = Team::updateOrCreate(
                ['code' => $club['code']],
                array_merge($club, [
                    // Placeholders; SquadImporter derives the real values.
                    'power' => 50,
                    'home_advantage' => 8,
                    'supporter_strength' => 60,
                    'goalkeeper_factor' => 50,
                ]),
            );

            $importer->import($team);
        }
    }
}
