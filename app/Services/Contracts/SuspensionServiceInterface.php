<?php

namespace App\Services\Contracts;

use App\Models\Game;
use App\Models\Team;

interface SuspensionServiceInterface
{
    /**
     * Players banned for this match: a red card in the team's previous
     * match, or an accumulated 3rd yellow picked up in it, costs the
     * player the next game.
     *
     * @return array<int, string> map of player id => reason ('red'|'yellows')
     */
    public function suspendedPlayers(Team $team, Game $game): array;
}
