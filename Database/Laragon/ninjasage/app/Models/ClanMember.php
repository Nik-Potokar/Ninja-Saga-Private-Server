<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClanMember extends Model
{
    protected $fillable = [
        'clan_id', 'character_id', 'role', 'stamina', 'max_stamina',
        'donated_golds', 'donated_tokens', 'prestige_boost_expires_at',
    ];

    protected $casts = [
        'prestige_boost_expires_at' => 'datetime',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }
}