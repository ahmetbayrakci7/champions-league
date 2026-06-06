<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tie extends Model
{
    protected $fillable = [
        'stage',
        'position',
        'home_team_id',
        'away_team_id',
        'winner_team_id',
        'home_penalties',
        'away_penalties',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'home_penalties' => 'integer',
            'away_penalties' => 'integer',
        ];
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class)->orderBy('leg');
    }
}
