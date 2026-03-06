<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClanBattle extends Model
{
    protected $fillable = [
        'attacker_clan_id', 'defender_clan_id', 'season_id',
        'attacker_won', 'battle_data',
    ];

    protected $casts = [
        'attacker_won' => 'boolean',
        'battle_data' => 'array',
    ];

    public function attackerClan()
    {
        return $this->belongsTo(Clan::class, 'attacker_clan_id');
    }

    public function defenderClan()
    {
        return $this->belongsTo(Clan::class, 'defender_clan_id');
    }
}
