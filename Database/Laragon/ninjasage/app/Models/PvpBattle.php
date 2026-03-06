<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PvpBattle extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_id',
        'enemy_id',
        'mode',
        'host_won',
        'trophy_delta',
        'host_trophy_before',
        'host_trophy_after',
        'enemy_trophy_before',
        'enemy_trophy_after',
        'host_level',
        'enemy_level',
        'host_rank',
        'enemy_rank',
        'host_snapshot',
        'enemy_snapshot',
        'battle_data',
    ];

    protected $casts = [
        'host_won' => 'boolean',
        'host_snapshot' => 'array',
        'enemy_snapshot' => 'array',
        'battle_data' => 'array',
    ];
}
