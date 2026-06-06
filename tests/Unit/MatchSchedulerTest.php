<?php

namespace Tests\Unit;

use App\Services\MatchScheduler;
use App\Services\Support\MtRandomGenerator;
use Tests\TestCase;

class MatchSchedulerTest extends TestCase
{
    private MatchScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduler = new MatchScheduler(new MtRandomGenerator());
    }

    /**
     * Realistic post-draw layout: every group has 4 distinct countries,
     * several associations spread clubs over multiple groups.
     *
     * @return array<string, array<int, string>>
     */
    private function groupCountries(): array
    {
        return [
            'A' => ['ESP', 'ENG', 'FRA', 'TUR'],
            'B' => ['ENG', 'ESP', 'GER', 'AZE'],
            'C' => ['GER', 'ITA', 'FRA', 'TUR'],
            'D' => ['ENG', 'GER', 'SCO', 'DEN'],
            'E' => ['ESP', 'POR', 'BEL', 'CZE'],
            'F' => ['ENG', 'POR', 'GRE', 'NOR'],
            'G' => ['ITA', 'POR', 'ESP', 'SCO'],
            'H' => ['FRA', 'GER', 'ENG', 'BEL'],
        ];
    }

    public function test_each_matchday_splits_groups_four_per_day(): void
    {
        $schedule = $this->scheduler->schedule($this->groupCountries(), 6);

        foreach (range(1, 6) as $week) {
            $days = array_map(fn (array $weeks) => $weeks[$week], $schedule);

            $this->assertCount(4, array_keys($days, 0, true), "Matchday {$week} Tuesday split broken");
            $this->assertCount(4, array_keys($days, 1, true), "Matchday {$week} Wednesday split broken");
        }
    }

    public function test_every_group_plays_both_days_across_the_stage(): void
    {
        $schedule = $this->scheduler->schedule($this->groupCountries(), 6);

        foreach ($schedule as $group => $weeks) {
            $this->assertContains(0, $weeks, "Group {$group} never plays Tuesday");
            $this->assertContains(1, $weeks, "Group {$group} never plays Wednesday");
        }
    }

    public function test_paired_same_association_groups_play_on_different_days(): void
    {
        // Simple feasible layout: each country has exactly two clubs.
        $groupCountries = [
            'A' => ['ESP', 'ENG'],
            'B' => ['ESP', 'GER'],
            'C' => ['ENG', 'ITA'],
            'D' => ['GER', 'ITA'],
            'E' => ['FRA', 'POR'],
            'F' => ['FRA', 'TUR'],
            'G' => ['POR', 'SCO'],
            'H' => ['TUR', 'SCO'],
        ];

        $schedule = $this->scheduler->schedule($groupCountries, 6);

        $pairs = [['A', 'B'], ['A', 'C'], ['B', 'D'], ['C', 'D'], ['E', 'F'], ['E', 'G'], ['F', 'H'], ['G', 'H']];

        foreach ($pairs as [$first, $second]) {
            foreach (range(1, 6) as $week) {
                $this->assertNotSame(
                    $schedule[$first][$week],
                    $schedule[$second][$week],
                    "Groups {$first}/{$second} share a day on matchday {$week} despite a same-association pair",
                );
            }
        }
    }

    public function test_falls_back_to_plain_split_when_pairing_is_unsatisfiable(): void
    {
        // One association in five groups: pairing all of them apart is impossible
        // alongside other constraints, but a 4/4 split must still come out.
        $groupCountries = [
            'A' => ['ENG'], 'B' => ['ENG'], 'C' => ['ENG'], 'D' => ['ENG'],
            'E' => ['ENG'], 'F' => ['ENG'], 'G' => ['ENG'], 'H' => ['ENG'],
        ];

        $schedule = $this->scheduler->schedule($groupCountries, 6);

        $days = array_map(fn (array $weeks) => $weeks[1], $schedule);

        $this->assertCount(4, array_keys($days, 0, true));
        $this->assertCount(4, array_keys($days, 1, true));
    }
}
