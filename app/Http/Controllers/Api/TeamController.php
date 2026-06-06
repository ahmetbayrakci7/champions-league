<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    /**
     * Current-season squad with EA SPORTS FC ratings plus tournament
     * form: appearances, goals, assists, cards and average match rating.
     */
    public function show(Team $team): JsonResponse
    {
        $players = $team->players()
            ->withCount([
                'appearances as apps',
                'events as goals' => fn ($query) => $query->where('type', 'goal'),
                'events as yellows' => fn ($query) => $query->where('type', 'yellow'),
                'events as reds' => fn ($query) => $query->where('type', 'red'),
                'assistEvents as assists',
            ])
            ->withAvg('appearances as avg_rating', 'rating')
            ->orderByDesc('overall')
            ->orderBy('name')
            ->get();

        return response()->json([
            'team' => $team->only([
                'id', 'name', 'code', 'color', 'country', 'pot', 'logo_url',
                'power', 'home_advantage', 'supporter_strength', 'goalkeeper_factor',
            ]),
            'players' => $players->map(fn ($player) => $player->only([
                'id', 'name', 'position', 'position_type', 'overall',
                'pace', 'shooting', 'passing', 'dribbling', 'defending', 'physical',
                'skill_moves', 'weak_foot', 'nationality', 'nationality_image', 'avatar_url',
            ]) + [
                'apps' => (int) $player->apps,
                'goals' => (int) $player->goals,
                'assists' => (int) $player->assists,
                'yellows' => (int) $player->yellows,
                'reds' => (int) $player->reds,
                'avg_rating' => $player->avg_rating !== null ? round((float) $player->avg_rating, 1) : null,
            ]),
        ]);
    }
}
