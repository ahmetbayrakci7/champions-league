<?php

namespace App\Services\Contracts;

use App\Models\Game;

interface LeagueServiceInterface
{
    /**
     * Full tournament snapshot. Before the draw: seeding pots.
     * After the draw: groups with standings, fixtures and predictions.
     *
     * @return array<string, mixed>
     */
    public function state(): array;

    /**
     * Run the group stage draw and generate every group's fixture.
     */
    public function drawGroups(): void;

    /**
     * Play the next unplayed matchday across all groups. Returns the
     * played week number, or null when the group stage is over.
     */
    public function playNextWeek(): ?int;

    /**
     * Play every remaining matchday until the group stage ends.
     */
    public function playAll(): void;

    /**
     * Wipe results and group assignments — back to the seeding pots.
     */
    public function reset(): void;

    /**
     * Manually override the score of an already played game.
     */
    public function updateGame(Game $game, int $homeGoals, int $awayGoals): Game;
}
