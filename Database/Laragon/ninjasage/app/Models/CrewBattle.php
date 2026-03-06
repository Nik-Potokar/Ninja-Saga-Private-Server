<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrewBattle extends Model
{
    protected $fillable = [
        'season_id', 'castle_id', 'attacker_crew_id', 'defender_crew_id',
        'attacker_won', 'battle_data',
    ];

    protected $casts = [
        'attacker_won' => 'boolean',
        'battle_data'  => 'array',
    ];
}
