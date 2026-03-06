<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewRequest extends Model
{
    protected $fillable = ['crew_id', 'character_id', 'status'];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }
}
