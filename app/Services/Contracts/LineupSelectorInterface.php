<?php

namespace App\Services\Contracts;

use App\Models\Team;
use Illuminate\Support\Collection;

interface LineupSelectorInterface
{
    /**
     * Pick a starting XI (1 GK + best balanced outfield) and a bench.
     *
     * @return array{starters: Collection<int, \App\Models\Player>, bench: Collection<int, \App\Models\Player>}
     */
    public function select(Team $team): array;
}
