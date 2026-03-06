<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrewSeason extends Model
{
    protected $fillable = ['number', 'active', 'phase', 'started_at', 'ended_at'];

    protected $casts = [
        'active'     => 'boolean',
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function castles(): HasMany
    {
        return $this->hasMany(CrewCastle::class, 'season_id');
    }
}
