<?php

namespace App\Services\Contracts;

use App\Models\Team;

interface TeamFormServiceInterface
{
    /**
     * Power multiplier from the squad's average match rating over the
     * recent games (config league.form). 1.0 = neutral form.
     */
    public function factor(Team $team): float;

    /**
     * Apply the form factor to the team's effective power in memory
     * (never persisted) so simulations and odds follow momentum.
     */
    public function adjust(Team $team): void;
}
