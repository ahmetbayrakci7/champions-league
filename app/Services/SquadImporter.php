<?php

namespace App\Services;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Imports a club's current-season squad from the bundled EA SPORTS FC
 * ratings snapshot (database/data/ea/team_{id}.json) and derives the
 * team's simulation attributes from its players:
 *
 *  - power:              average overall of the best 18 players
 *  - goalkeeper_factor:  best goalkeeper's overall (FAQ #6)
 *  - supporter_strength: power-based heuristic (big clubs draw big crowds)
 *  - home_advantage:     8-13% scaled with supporter strength
 */
class SquadImporter
{
    public function import(Team $team): void
    {
        $snapshot = $this->loadSnapshot($team);

        $players = collect($snapshot['players']);

        foreach ($players as $player) {
            Player::updateOrCreate(
                ['team_id' => $team->id, 'ea_player_id' => $player['ea_id']],
                [
                    'name' => $player['common_name'] ?: trim($player['first_name'].' '.$player['last_name']),
                    'position' => $player['position'],
                    // EA files keepers under "defense"; normalise so the
                    // goalkeeper factor and the UI can rely on the type.
                    'position_type' => $player['position'] === 'GK' ? 'goalkeeper' : $player['position_type'],
                    'overall' => $player['overall'],
                    'pace' => $player['stats']['pace'] ?? null,
                    'shooting' => $player['stats']['shooting'] ?? null,
                    'passing' => $player['stats']['passing'] ?? null,
                    'dribbling' => $player['stats']['dribbling'] ?? null,
                    'defending' => $player['stats']['defending'] ?? null,
                    'physical' => $player['stats']['physical'] ?? null,
                    'skill_moves' => $player['skill_moves'],
                    'weak_foot' => $player['weak_foot'],
                    'nationality' => $player['nationality'],
                    'nationality_image' => $player['nationality_image'],
                    'avatar_url' => $player['avatar_url'],
                    'birthdate' => $this->parseBirthdate($player['birthdate'] ?? null),
                ],
            );
        }

        $team->update(array_merge(
            ['logo_url' => $snapshot['team']['logo_url'] ?? $team->logo_url],
            $this->deriveAttributes($players),
        ));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $players
     * @return array<string, int>
     */
    public function deriveAttributes($players): array
    {
        $overalls = $players->pluck('overall')->sortDesc()->take(18);
        $power = (int) round($overalls->avg() ?? 50);

        $bestKeeper = $players
            ->filter(fn ($player) => ($player['position'] ?? null) === 'GK')
            ->max('overall') ?? $power;

        $supporters = min(100, $power + 10);

        return [
            'power' => $power,
            'goalkeeper_factor' => (int) $bestKeeper,
            'supporter_strength' => $supporters,
            'home_advantage' => (int) round(8 + ($supporters - 60) / 8),
        ];
    }

    /**
     * @return array{team: array<string, mixed>, players: array<int, array<string, mixed>>}
     */
    private function loadSnapshot(Team $team): array
    {
        $path = $this->snapshotPath($team->ea_team_id);

        if (! is_file($path)) {
            throw new RuntimeException("No EA snapshot for team {$team->name} ({$team->ea_team_id}). Run league:import-squads --refresh.");
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function snapshotPath(?int $eaTeamId): string
    {
        return database_path("data/ea/team_{$eaTeamId}.json");
    }

    private function parseBirthdate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
