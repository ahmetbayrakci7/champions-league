<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'stage',
        'tie_id',
        'leg',
        'week',
        'kickoff_at',
        'home_team_id',
        'away_team_id',
        'home_goals',
        'away_goals',
        'is_played',
    ];

    protected function casts(): array
    {
        return [
            'week' => 'integer',
            // No timezone suffix: kickoff is a fixed "stadium clock" time,
            // it must not shift with the viewer's timezone.
            'kickoff_at' => 'datetime:Y-m-d\TH:i:s',
            'home_goals' => 'integer',
            'away_goals' => 'integer',
            'is_played' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function tie(): BelongsTo
    {
        return $this->belongsTo(Tie::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class)->orderBy('minute')->orderBy('id');
    }

    public function appearances(): HasMany
    {
        return $this->hasMany(Appearance::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
}
