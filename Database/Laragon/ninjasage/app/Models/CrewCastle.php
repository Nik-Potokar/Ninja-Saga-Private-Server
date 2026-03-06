<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewCastle extends Model
{
    protected $fillable = [
        'season_id', 'castle_index', 'name', 'owner_crew_id',
        'wall_hp', 'defender_hp', 'last_recovery_at',
    ];

    protected $casts = [
        'last_recovery_at' => 'datetime',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(CrewSeason::class, 'season_id');
    }

    public function ownerCrew(): BelongsTo
    {
        return $this->belongsTo(Crew::class, 'owner_crew_id');
    }
}
