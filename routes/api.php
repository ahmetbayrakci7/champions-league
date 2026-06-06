<?php

use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\KnockoutController;
use App\Http\Controllers\Api\LeagueController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/league', [LeagueController::class, 'show']);
Route::post('/league/draw', [LeagueController::class, 'draw']);
Route::post('/league/play-week', [LeagueController::class, 'playWeek']);
Route::post('/league/play-all', [LeagueController::class, 'playAll']);
Route::post('/league/reset', [LeagueController::class, 'reset']);

Route::post('/knockout/advance', [KnockoutController::class, 'advance']);
Route::post('/knockout/advance-all', [KnockoutController::class, 'advanceAll']);

Route::get('/stats', [StatsController::class, 'index']);
Route::get('/teams/{team}', [TeamController::class, 'show']);
Route::get('/games/{game}', [GameController::class, 'show']);
Route::put('/games/{game}', [GameController::class, 'update']);
