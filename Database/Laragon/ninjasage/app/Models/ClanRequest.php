<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClanRequest extends Model
{
    protected $fillable = ['clan_id', 'character_id', 'status'];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }
}