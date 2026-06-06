<?php

namespace App\Services\Support;

use App\Services\Contracts\RandomGeneratorInterface;

class MtRandomGenerator implements RandomGeneratorInterface
{
    public function float(): float
    {
        return mt_rand() / (mt_getrandmax() + 1);
    }
}
