<?php

namespace Tests\Support;

use App\Services\Contracts\RandomGeneratorInterface;

/**
 * Deterministic RNG for tests: replays a fixed sequence of floats.
 */
class SequenceRandomGenerator implements RandomGeneratorInterface
{
    private int $index = 0;

    /**
     * @param  array<int, float>  $sequence
     */
    public function __construct(
        private readonly array $sequence,
    ) {
    }

    public function float(): float
    {
        $value = $this->sequence[$this->index % count($this->sequence)];
        $this->index++;

        return $value;
    }
}
