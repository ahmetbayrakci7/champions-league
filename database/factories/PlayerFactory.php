<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'ea_player_id' => fake()->unique()->numberBetween(1, 999999),
            'name' => fake()->name(),
            'position' => fake()->randomElement(['GK', 'CB', 'LB', 'RB', 'CDM', 'CM', 'CAM', 'LW', 'RW', 'ST']),
            'position_type' => fake()->randomElement(['goalkeeper', 'defense', 'midfielder', 'attack']),
            'overall' => fake()->numberBetween(55, 92),
            'pace' => fake()->numberBetween(40, 95),
            'shooting' => fake()->numberBetween(30, 92),
            'passing' => fake()->numberBetween(40, 92),
            'dribbling' => fake()->numberBetween(40, 93),
            'defending' => fake()->numberBetween(25, 90),
            'physical' => fake()->numberBetween(40, 92),
            'skill_moves' => fake()->numberBetween(1, 5),
            'weak_foot' => fake()->numberBetween(1, 5),
            'nationality' => fake()->country(),
        ];
    }
}
