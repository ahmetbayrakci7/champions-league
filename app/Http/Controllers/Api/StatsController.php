<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\StatsServiceInterface;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __construct(
        private readonly StatsServiceInterface $stats,
    ) {
    }

    /**
     * Tournament leaderboards: players and teams.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->stats->overview());
    }
}
