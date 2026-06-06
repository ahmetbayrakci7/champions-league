<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'color',
        'country',
        'pot',
        'ea_team_id',
        'logo_url',
        'group_id',
        'power',
        'home_advantage',
        'supporter_strength',
        'goalkeeper_factor',
    ];

    protected function casts(): array
    {
        return [
            'pot' => 'integer',
            'power' => 'integer',
            'home_advantage' => 'integer',
            'supporter_strength' => 'integer',
            'goalkeeper_factor' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function homeGames(): HasMany
    {
        return $this->hasMany(Game::class, 'home_team_id');
    }

    public function awayGames(): HasMany
    {
        return $this->hasMany(Game::class, 'away_team_id');
    }
}
