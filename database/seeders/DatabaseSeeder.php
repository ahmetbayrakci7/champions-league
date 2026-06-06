<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the 32 clubs with their EA squads. Groups and fixtures are
     * created later by the draw (POST /api/league/draw).
     */
    public function run(): void
    {
        $this->call(TeamSeeder::class);
    }
}
