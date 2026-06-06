<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Services\DrawService;
use App\Services\Support\MtRandomGenerator;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Tests\TestCase;

class DrawServiceTest extends TestCase
{
    private function drawService(): DrawService
    {
        return new DrawService(new MtRandomGenerator());
    }

    /**
     * 32 teams mirroring the real seeding: England has five clubs spread
     * over the pots, so the same-country rule genuinely bites.
     *
     * @return Collection<int, Team>
     */
    private function teams(): Collection
    {
        $countriesPerPot = [
            1 => ['ESP', 'ENG', 'GER', 'FRA', 'ENG', 'ESP', 'ENG', 'ITA'],
            2 => ['ENG', 'ESP', 'GER', 'ITA', 'GER', 'POR', 'POR', 'POR'],
            3 => ['ENG', 'ESP', 'GER', 'FRA', 'FRA', 'SCO', 'BEL', 'GRE'],
            4 => ['TUR', 'TUR', 'SCO', 'BEL', 'DEN', 'CZE', 'NOR', 'AZE'],
        ];

        $teams = collect();
        $id = 0;

        foreach ($countriesPerPot as $pot => $countries) {
            foreach ($countries as $country) {
                $team = new Team([
                    'name' => "Club {$id}",
                    'code' => 'C'.str_pad((string) $id, 2, '0', STR_PAD_LEFT),
                    'country' => $country,
                    'pot' => $pot,
                ]);
                $team->id = ++$id;
                $teams->push($team);
            }
        }

        return $teams;
    }

    public function test_produces_eight_groups_of_four(): void
    {
        $result = $this->drawService()->draw($this->teams());

        $this->assertSame(config('league.group_names'), array_keys($result));

        foreach ($result as $teamIds) {
            $this->assertCount(4, $teamIds);
        }

        $all = collect($result)->flatten();
        $this->assertCount(32, $all);
        $this->assertCount(32, $all->unique());
    }

    public function test_every_group_has_one_team_per_pot(): void
    {
        $teams = $this->teams()->keyBy('id');
        $result = $this->drawService()->draw($this->teams());

        foreach ($result as $groupName => $teamIds) {
            $pots = collect($teamIds)->map(fn (int $id) => $teams[$id]->pot)->sort()->values()->all();

            $this->assertSame([1, 2, 3, 4], $pots, "Group {$groupName} violates pot seeding");
        }
    }

    public function test_no_group_contains_two_teams_from_the_same_country(): void
    {
        $teams = $this->teams()->keyBy('id');

        // The shuffle is random; verify the invariant across many draws.
        for ($i = 0; $i < 25; $i++) {
            $result = $this->drawService()->draw($this->teams());

            foreach ($result as $groupName => $teamIds) {
                $countries = collect($teamIds)->map(fn (int $id) => $teams[$id]->country);

                $this->assertCount(
                    4,
                    $countries->unique(),
                    "Group {$groupName} drew a same-country clash: {$countries->implode(',')}",
                );
            }
        }
    }

    public function test_rejects_unbalanced_pots(): void
    {
        $teams = $this->teams()->reject(fn (Team $team) => $team->id === 1)->values();

        $this->expectException(InvalidArgumentException::class);

        $this->drawService()->draw($teams);
    }
}
