<?php

namespace App\Services\Contracts;

use App\Models\Game;
use App\Models\Team;

interface InjuryServiceInterface
{
    /**
     * Players ruled out of this match through injury.
     *
     * @return array<int, int> map of player id => matches still to miss (including this one)
     */
    public function injuredPlayers(Team $team, Game $game): array;
}
