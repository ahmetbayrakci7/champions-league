<?php

namespace App\Services\Support;

use App\Services\Contracts\RandomGeneratorInterface;

trait SamplesPoisson
{
    /**
     * Knuth's algorithm for sampling a Poisson-distributed goal count.
     */
    private function poisson(float $lambda, RandomGeneratorInterface $random): int
    {
        $limit = exp(-$lambda);
        $product = 1.0;
        $count = -1;

        do {
            $count++;
            $product *= $random->float();
        } while ($product > $limit);

        return min($count, (int) config('league.max_goals_per_side'));
    }
}
