<?php

namespace App\Services\Contracts;

interface StatsServiceInterface
{
    /**
     * Tournament-wide player and team leaderboards across every
     * played match (group stage + knockout).
     *
     * @return array<string, mixed>
     */
    public function overview(): array;
}
