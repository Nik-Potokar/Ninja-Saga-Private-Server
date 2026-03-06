<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShadowWarBattle extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'battle_code',
        'character_id',
        'enemy_id',
        'character_squad',
        'enemy_squad',
        'character_level',
        'enemy_level',
        'character_rank',
        'enemy_rank',
        'character_trophy_before',
        'enemy_trophy_before',
        'character_trophy_after',
        'enemy_trophy_after',
        'trophy_delta',
        'total_damage',
        'won',
        'battle_data',
        'energy_cost',
        'energy_before',
        'energy_after',
        'refills_used_today',
    ];

    protected $casts = [
        'won' => 'boolean',
        'battle_data' => 'array',
    ];
}
