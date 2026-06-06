<?php

namespace App\Services\Contracts;

interface RandomGeneratorInterface
{
    /**
     * Uniform random float in [0, 1).
     */
    public function float(): float;
}
