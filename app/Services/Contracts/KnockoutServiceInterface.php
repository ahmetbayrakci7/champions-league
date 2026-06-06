<?php

namespace App\Services\Contracts;

interface KnockoutServiceInterface
{
    /**
     * Bracket snapshot: stages with ties, aggregates, penalties, the
     * champion and what the next user action is.
     *
     * @return array<string, mixed>
     */
    public function state(): array;

    /**
     * Advance the knockout phase one step: draw the Round of 16 when
     * the group stage finishes, otherwise play the next leg, resolve
     * finished ties (extra time + penalties) and seed the next round.
     */
    public function advance(): void;

    /**
     * Play the whole knockout phase to the end: draw if needed, then
     * every remaining leg through to the final — champion decided.
     */
    public function advanceAll(): void;
}
