<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewMember extends Model
{
    protected $fillable = [
        'crew_id', 'character_id', 'role', 'battle_role', 'role_limit_at',
        'stamina', 'max_stamina', 'donated_golds', 'donated_tokens',
        'minigame_energy', 'prestige_boost_expires_at',
    ];

    protected $casts = [
        'prestige_boost_expires_at' => 'datetime',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }
}
