<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appearance extends Model
{
    protected $fillable = [
        'game_id',
        'team_id',
        'player_id',
        'is_starting',
        'came_on',
        'went_off',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'is_starting' => 'boolean',
            'came_on' => 'integer',
            'went_off' => 'integer',
            'rating' => 'float',
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
}
