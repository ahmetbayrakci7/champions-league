<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\KnockoutServiceInterface;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class KnockoutController extends Controller
{
    public function __construct(
        private readonly KnockoutServiceInterface $knockout,
    ) {
    }

    /**
     * Advance the knockout phase one step (draw R16 / play next leg).
     */
    public function advance(): JsonResponse
    {
        try {
            $this->knockout->advance();
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json($this->knockout->state());
    }
}
