<?php

namespace App\Services\Contracts;

use Illuminate\Support\Collection;

interface DrawServiceInterface
{
    /**
     * Draw the group stage: eight groups of four, one club from each
     * seeding pot, never two clubs from the same association together.
     *
     * @param  Collection<int, \App\Models\Team>  $teams  32 teams with pot & country
     * @return array<string, array<int, int>> map of group name => team ids (pot 1 first)
     */
    public function draw(Collection $teams): array;
}
