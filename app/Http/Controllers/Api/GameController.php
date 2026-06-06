<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateGameRequest;
use App\Models\Game;
use App\Services\Contracts\InjuryServiceInterface;
use App\Services\Contracts\LeagueServiceInterface;
use App\Services\Contracts\MatchSimulatorInterface;
use App\Services\Contracts\SuspensionServiceInterface;
use App\Services\Contracts\TeamFormServiceInterface;
use Illuminate\Http\JsonResponse;

class GameController extends Controller
{
    public function __construct(
        private readonly LeagueServiceInterface $league,
        private readonly MatchSimulatorInterface $simulator,
        private readonly SuspensionServiceInterface $suspensions,
        private readonly InjuryServiceInterface $injuries,
        private readonly TeamFormServiceInterface $form,
    ) {
    }

    /**
     * Match centre detail: pre-match win odds, lineups with ratings
     * and the minute-by-minute event timeline.
     */
    public function show(Game $game): JsonResponse
    {
        $game->load([
            'homeTeam:id,name,code,color,logo_url,power,goalkeeper_factor',
            'awayTeam:id,name,code,color,logo_url,power,goalkeeper_factor',
            'events.player:id,name,position,avatar_url',
            'events.relatedPlayer:id,name',
            'appearances.player:id,name,position,position_type,overall,avatar_url',
            'tie:id,stage,position,home_team_id,away_team_id,home_penalties,away_penalties,winner_team_id',
        ]);

        $lineup = fn (int $teamId) => $game->appearances
            ->where('team_id', $teamId)
            ->map(fn ($appearance) => [
                'player_id' => $appearance->player_id,
                'name' => $appearance->player?->name,
                'position' => $appearance->player?->position,
                'position_type' => $appearance->player?->position_type,
                'overall' => $appearance->player?->overall,
                'avatar_url' => $appearance->player?->avatar_url,
                'is_starting' => $appearance->is_starting,
                'came_on' => $appearance->came_on,
                'went_off' => $appearance->went_off,
                'rating' => $appearance->rating,
            ])
            ->sortBy([['is_starting', 'desc'], ['rating', 'desc']])
            ->values();

        // Momentum-adjusted odds: clones keep the payload's raw powers.
        $homeForm = clone $game->homeTeam;
        $awayForm = clone $game->awayTeam;
        $this->form->adjust($homeForm);
        $this->form->adjust($awayForm);

        $suspended = fn ($team) => collect($this->suspensions->suspendedPlayers($team, $game))
            ->map(fn (string $reason, int $playerId) => [
                'reason' => $reason,
                'player' => $team->players()->select('id', 'name', 'position', 'avatar_url')->find($playerId),
            ])
            ->filter(fn (array $row) => $row['player'] !== null)
            ->values();

        $injured = fn ($team) => collect($this->injuries->injuredPlayers($team, $game))
            ->map(fn (int $remaining, int $playerId) => [
                'matches_left' => $remaining,
                'player' => $team->players()->select('id', 'name', 'position', 'avatar_url')->find($playerId),
            ])
            ->filter(fn (array $row) => $row['player'] !== null)
            ->values();

        return response()->json([
            'game' => $game->only([
                'id', 'week', 'stage', 'leg', 'is_played',
                'home_goals', 'away_goals',
            ]) + [
                'kickoff_at' => $game->kickoff_at?->format('Y-m-d\TH:i:s'),
                'home_team' => $game->homeTeam,
                'away_team' => $game->awayTeam,
                'tie' => $game->tie,
            ],
            'probabilities' => $this->simulator->probabilities(
                $homeForm,
                $awayForm,
                $game->stage === 'final',
            ),
            'form' => [
                'home' => round($this->form->factor($game->homeTeam), 3),
                'away' => round($this->form->factor($game->awayTeam), 3),
            ],
            'suspensions' => [
                'home' => $suspended($game->homeTeam),
                'away' => $suspended($game->awayTeam),
            ],
            'injuries' => [
                'home' => $injured($game->homeTeam),
                'away' => $injured($game->awayTeam),
            ],
            'events' => $game->events,
            'lineups' => [
                'home' => $lineup($game->home_team_id),
                'away' => $lineup($game->away_team_id),
            ],
        ]);
    }

    /**
     * Edit the score of an already played group game; the match is
     * replayed with the new score fixed, so the timeline and ratings
     * stay consistent and the standings recalculate automatically.
     */
    public function update(UpdateGameRequest $request, Game $game): JsonResponse
    {
        if (! $game->is_played) {
            return response()->json(['message' => 'Only played games can be edited.'], 409);
        }

        if ($game->stage !== 'group') {
            return response()->json(['message' => 'Knockout results cannot be edited — they decide the bracket.'], 409);
        }

        $this->league->updateGame(
            $game,
            (int) $request->validated('home_goals'),
            (int) $request->validated('away_goals'),
        );

        return response()->json($this->league->state());
    }
}
