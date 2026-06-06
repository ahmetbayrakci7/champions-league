<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Group;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'week' => fake()->numberBetween(1, 6),
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'home_goals' => null,
            'away_goals' => null,
            'is_played' => false,
        ];
    }

    public function played(?int $homeGoals = null, ?int $awayGoals = null): static
    {
        return $this->state(fn () => [
            'home_goals' => $homeGoals ?? fake()->numberBetween(0, 5),
            'away_goals' => $awayGoals ?? fake()->numberBetween(0, 5),
            'is_played' => true,
        ]);
    }
}
