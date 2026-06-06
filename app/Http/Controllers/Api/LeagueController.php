<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\LeagueServiceInterface;
use Illuminate\Http\JsonResponse;

class LeagueController extends Controller
{
    public function __construct(
        private readonly LeagueServiceInterface $league,
    ) {
    }

    /**
     * Current league snapshot: standings, fixtures, predictions.
     */
    public function show(): JsonResponse
    {
        return response()->json($this->league->state());
    }

    /**
     * Run the group stage draw (FAQ #4): 8 groups of 4 from seeding pots.
     */
    public function draw(): JsonResponse
    {
        $this->league->drawGroups();

        return response()->json($this->league->state());
    }

    /**
     * Simulate the next unplayed week.
     */
    public function playWeek(): JsonResponse
    {
        $week = $this->league->playNextWeek();

        if ($week === null) {
            return response()->json(['message' => 'Season is already over.'], 409);
        }

        return response()->json($this->league->state());
    }

    /**
     * Simulate every remaining week.
     */
    public function playAll(): JsonResponse
    {
        $this->league->playAll();

        return response()->json($this->league->state());
    }

    /**
     * Wipe all results and regenerate the fixture.
     */
    public function reset(): JsonResponse
    {
        $this->league->reset();

        return response()->json($this->league->state());
    }
}
