<?php

namespace App\Services\Contracts;

interface MatchSchedulerInterface
{
    /**
     * Split the groups between Tuesday (0) and Wednesday (1) for every
     * matchday: four groups per day, clubs from the same association
     * paired onto different days (FAQ #4), days rotating week to week.
     *
     * @param  array<string, array<int, string>>  $groupCountries  group name => association codes
     * @param  int  $weeks  number of matchdays
     * @return array<string, array<int, int>> group name => [week => 0|1]
     */
    public function schedule(array $groupCountries, int $weeks): array;
}
