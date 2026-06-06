<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\SquadImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Imports current-season squads from EA SPORTS FC official ratings.
 *
 * By default the bundled snapshots in database/data/ea are used, so the
 * project works offline and deploys reproducibly. Pass --refresh to
 * re-fetch every squad live from ea.com and update the snapshots.
 */
class ImportSquads extends Command
{
    protected $signature = 'league:import-squads {--refresh : Fetch fresh data from ea.com before importing}';

    protected $description = 'Import club squads and ratings from EA SPORTS FC data';

    public function handle(SquadImporter $importer): int
    {
        $teams = Team::orderBy('pot')->orderBy('name')->get();

        if ($teams->isEmpty()) {
            $this->error('No teams found. Run the seeder first (php artisan db:seed).');

            return self::FAILURE;
        }

        foreach ($teams as $team) {
            if ($this->option('refresh')) {
                $this->refreshSnapshot($team, $importer);
            }

            $importer->import($team);

            $this->line(sprintf(
                '  <info>%s</info> — %d players, power %d, GK %d',
                $team->name,
                $team->players()->count(),
                $team->power,
                $team->goalkeeper_factor,
            ));
        }

        $this->info('Squads imported.');

        return self::SUCCESS;
    }

    private function refreshSnapshot(Team $team, SquadImporter $importer): void
    {
        $this->line("  fetching {$team->name} from ea.com…");

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
        ])->timeout(30)->get('https://www.ea.com/games/ea-sports-fc/ratings', [
            'team' => $team->ea_team_id,
        ]);

        if (! $response->successful()) {
            $this->warn("    EA returned {$response->status()}, keeping bundled snapshot.");

            return;
        }

        if (! preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $response->body(), $match)) {
            $this->warn('    Could not locate ratings payload, keeping bundled snapshot.');

            return;
        }

        $data = json_decode($match[1], true);
        $items = $data['props']['pageProps']['ratingDetails']['items'] ?? [];

        if ($items === []) {
            $this->warn('    Empty squad payload, keeping bundled snapshot.');

            return;
        }

        $snapshot = [
            'team' => [
                'ea_id' => $items[0]['team']['id'],
                'name' => $items[0]['team']['label'],
                'logo_url' => $items[0]['team']['imageUrl'],
                'league' => $items[0]['leagueName'] ?? null,
            ],
            'players' => array_map(fn (array $player): array => $this->mapPlayer($player), $items),
        ];

        file_put_contents(
            $importer->snapshotPath($team->ea_team_id),
            json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    /**
     * @param  array<string, mixed>  $player
     * @return array<string, mixed>
     */
    private function mapPlayer(array $player): array
    {
        $stats = array_map(fn (array $stat) => $stat['value'], $player['stats'] ?? []);

        $isKeeper = ($player['position']['shortLabel'] ?? '') === 'GK';

        $avg = fn (array $keys): ?int => ($values = array_filter(array_map(fn ($key) => $stats[$key] ?? null, $keys), fn ($v) => $v !== null)) === []
            ? null
            : (int) round(array_sum($values) / count($values));

        return [
            'ea_id' => $player['id'],
            'first_name' => $player['firstName'],
            'last_name' => $player['lastName'],
            'common_name' => $player['commonName'],
            'overall' => $player['overallRating'],
            'position' => $player['position']['shortLabel'],
            'position_label' => $player['position']['label'],
            'position_type' => $isKeeper ? 'goalkeeper' : $player['position']['positionType']['id'],
            'nationality' => $player['nationality']['label'] ?? null,
            'nationality_image' => $player['nationality']['imageUrl'] ?? null,
            'avatar_url' => $player['avatarUrl'] ?? null,
            'skill_moves' => $player['skillMoves'] ?? null,
            'weak_foot' => $player['weakFootAbility'] ?? null,
            'height' => $player['height'] ?? null,
            'birthdate' => $player['birthdate'] ?? null,
            'stats' => $isKeeper
                ? [
                    'pace' => $stats['gkDiving'] ?? null,
                    'shooting' => $stats['gkHandling'] ?? null,
                    'passing' => $stats['gkKicking'] ?? null,
                    'dribbling' => $stats['gkPositioning'] ?? null,
                    'defending' => $stats['gkReflexes'] ?? null,
                    'physical' => $avg(['jumping', 'stamina', 'strength', 'aggression']),
                ]
                : [
                    'pace' => $avg(['acceleration', 'sprintSpeed']),
                    'shooting' => $avg(['finishing', 'shotPower', 'longShots', 'positioning', 'penalties', 'volleys']),
                    'passing' => $avg(['vision', 'crossing', 'freeKickAccuracy', 'shortPassing', 'longPassing', 'curve']),
                    'dribbling' => $avg(['agility', 'balance', 'reactions', 'ballControl', 'dribbling', 'composure']),
                    'defending' => $avg(['interceptions', 'headingAccuracy', 'defensiveAwareness', 'standingTackle', 'slidingTackle']),
                    'physical' => $avg(['jumping', 'stamina', 'strength', 'aggression']),
                ],
        ];
    }
}
