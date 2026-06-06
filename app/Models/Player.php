<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'ea_player_id',
        'name',
        'position',
        'position_type',
        'overall',
        'pace',
        'shooting',
        'passing',
        'dribbling',
        'defending',
        'physical',
        'skill_moves',
        'weak_foot',
        'nationality',
        'nationality_image',
        'avatar_url',
        'birthdate',
    ];

    protected function casts(): array
    {
        return [
            'overall' => 'integer',
            'birthdate' => 'date',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function appearances(): HasMany
    {
        return $this->hasMany(Appearance::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class);
    }

    /**
     * Goal events this player assisted (related_player_id on goals).
     */
    public function assistEvents(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'related_player_id')
            ->where('type', MatchEvent::TYPE_GOAL);
    }
}
