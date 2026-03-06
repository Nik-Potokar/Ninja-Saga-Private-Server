<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Giveaway extends Model
{
    protected $fillable = [
        'title',
        'description',
        'prizes',
        'requirements',
        'ends_at',
        'winners',
    ];

    protected $casts = [
        'prizes'       => 'array',
        'requirements' => 'array',
        'winners'      => 'array',
        'ends_at'      => 'datetime',
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(GiveawayParticipant::class);
    }

    public function isActive(): bool
    {
        return $this->ends_at->isFuture();
    }

    public function hasWinners(): bool
    {
        return !empty($this->winners);
    }
}
