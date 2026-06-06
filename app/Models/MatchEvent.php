<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchEvent extends Model
{
    public const TYPE_GOAL = 'goal';

    public const TYPE_YELLOW = 'yellow';

    public const TYPE_RED = 'red';

    public const TYPE_INJURY = 'injury';

    public const TYPE_SUB = 'sub';

    protected $fillable = [
        'game_id',
        'team_id',
        'player_id',
        'related_player_id',
        'minute',
        'type',
        'commentary',
        'template',
        'params',
    ];

    protected function casts(): array
    {
        return [
            'minute' => 'integer',
            'params' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function relatedPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'related_player_id');
    }
}
