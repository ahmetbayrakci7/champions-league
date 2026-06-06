<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' FC',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'color' => fake()->hexColor(),
            'country' => strtoupper(fake()->lexify('???')),
            'pot' => fake()->numberBetween(1, 4),
            'power' => fake()->numberBetween(40, 95),
            'home_advantage' => fake()->numberBetween(5, 15),
            'supporter_strength' => fake()->numberBetween(50, 100),
            'goalkeeper_factor' => fake()->numberBetween(50, 95),
        ];
    }
}
